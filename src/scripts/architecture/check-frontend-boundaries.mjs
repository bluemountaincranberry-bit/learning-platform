import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import ts from 'typescript';

const args = process.argv.slice(2);
const defaultRoot = path.resolve(import.meta.dirname, '../../resources/js/spa');
const root = path.resolve(args.find((arg) => !arg.startsWith('--')) ?? defaultRoot);
const baselinePath = path.resolve(args.filter((arg) => !arg.startsWith('--'))[1] ?? path.resolve(import.meta.dirname, '../../architecture/frontend-boundary-baseline.json'));

if (args.includes('--self-test')) {
    const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'blue-ts-boundary-'));
    fs.mkdirSync(path.join(fixture, 'pages'), { recursive: true });
    fs.mkdirSync(path.join(fixture, 'shared/ui'), { recursive: true });
    fs.mkdirSync(path.join(fixture, 'domains/content/api'), { recursive: true });
    fs.writeFileSync(path.join(fixture, 'pages/BadPage.ts'), "import { api } from '../api/contentApi';\n");
    fs.writeFileSync(path.join(fixture, 'shared/ui/Bad.ts'), "import { content } from '../../domains/content';\n");
    const rules = inspect(fixture).map((item) => item.rule);
    fs.rmSync(fixture, { recursive: true });
    if (!rules.includes('page-direct-http') || !rules.includes('shared-business-import')) {
        console.error('Self-test failed: page HTTP or shared business import was not detected.');
        process.exit(1);
    }
    console.log('Frontend boundary self-test passed.');
    process.exit(0);
}

const violations = inspect(root).map(key).sort();
if (args.includes('--write-baseline')) {
    fs.mkdirSync(path.dirname(baselinePath), { recursive: true });
    fs.writeFileSync(baselinePath, JSON.stringify({ description: 'Exact pre-existing frontend boundary violations. New entries fail the check.', violations }, null, 2) + '\n');
    console.log(`Wrote ${violations.length} frontend baseline violations.`);
    process.exit(0);
}
const baseline = fs.existsSync(baselinePath) ? JSON.parse(fs.readFileSync(baselinePath, 'utf8')) : { violations: [] };
const known = new Set(baseline.violations);
const additions = violations.filter((item) => !known.has(item));
if (additions.length) {
    additions.forEach((item) => console.error(item));
    process.exit(1);
}
console.log(`Frontend boundaries passed: ${violations.length} known violations.`);

function inspect(sourceRoot) {
    const violations = [];
    for (const file of walk(sourceRoot)) {
        if (!/\.(ts|vue)$/.test(file)) continue;
        const relative = path.relative(sourceRoot, file).replaceAll(path.sep, '/');
        const source = fs.readFileSync(file, 'utf8');
        const imports = ts.preProcessFile(source).importedFiles.map((entry) => entry.fileName);
        for (const specifier of imports) {
            const resolved = resolveImport(file, specifier, sourceRoot);
            const target = resolved ? path.relative(sourceRoot, resolved).replaceAll(path.sep, '/') : specifier;
            if (relative.startsWith('pages/') && (target.startsWith('api/') || target.includes('/api/'))) {
                violations.push({ file: relative, target, rule: 'page-direct-http' });
            }
            if (relative.startsWith('shared/') && target.startsWith('domains/')) {
                violations.push({ file: relative, target, rule: 'shared-business-import' });
            }
            if (relative.startsWith('infrastructure/') && target.startsWith('domains/')) {
                violations.push({ file: relative, target, rule: 'infrastructure-business-import' });
            }
            const sourceDomain = relative.match(/^domains\/([^/]+)\//)?.[1];
            const targetDomain = target.match(/^domains\/([^/]+)\//)?.[1];
            if (sourceDomain && targetDomain && sourceDomain !== targetDomain && !/^domains\/[^/]+\/index\.ts$/.test(target)) {
                violations.push({ file: relative, target, rule: 'private-cross-domain-import' });
            }
        }
    }
    return violations;
}

function resolveImport(sourceFile, specifier, sourceRoot) {
    let candidate;
    if (specifier.startsWith('@/')) candidate = path.join(sourceRoot, specifier.slice(2).replace(/^spa\//, ''));
    else if (specifier.startsWith('.')) candidate = path.resolve(path.dirname(sourceFile), specifier);
    else return null;
    for (const suffix of ['', '.ts', '.vue', '/index.ts']) {
        if (fs.existsSync(candidate + suffix)) return candidate + suffix;
    }
    return candidate;
}

function* walk(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const target = path.join(directory, entry.name);
        if (entry.isDirectory()) yield* walk(target);
        else yield target;
    }
}

function key(item) {
    return `${item.rule}|${item.file}|${item.target}`;
}
