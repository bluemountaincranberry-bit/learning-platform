import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const args = process.argv.slice(2);
const positional = args.filter((arg) => !arg.startsWith('--'));
const projectRoot = path.resolve(positional[0] ?? path.resolve(import.meta.dirname, '../..'));
const baselinePath = path.resolve(positional[1] ?? path.join(projectRoot, 'architecture/naming-baseline.json'));

if (args.includes('--self-test')) {
    const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'blue-naming-'));
    fs.mkdirSync(path.join(fixture, 'app/Modules/Test/Application'), { recursive: true });
    fs.mkdirSync(path.join(fixture, 'resources/js/spa/domains/test/model'), { recursive: true });
    fs.mkdirSync(path.join(fixture, 'resources/js/spa/shared/ui'), { recursive: true });
    fs.writeFileSync(path.join(fixture, 'app/Modules/Test/Application/Wrong.php'), '<?php class OtherName {}');
    fs.writeFileSync(path.join(fixture, 'app/Modules/Test/Application/CommonManager.php'), '<?php class CommonManager {}');
    fs.writeFileSync(path.join(fixture, 'resources/js/spa/domains/test/model/query.ts'), 'export {};');
    fs.writeFileSync(path.join(fixture, 'resources/js/spa/shared/ui/bad_button.vue'), '<template />');
    const rules = inspect(fixture).map((item) => item.rule);
    fs.rmSync(fixture, { recursive: true });
    for (const expected of ['php-file-symbol-mismatch', 'forbidden-generic-name', 'domain-file-convention', 'vue-component-filename']) {
        if (!rules.includes(expected)) {
            console.error(`Naming self-test failed: ${expected} was not detected.`);
            process.exit(1);
        }
    }
    console.log('Naming self-test passed.');
    process.exit(0);
}

const violations = inspect(projectRoot).map(key).sort();
if (args.includes('--write-baseline')) {
    fs.mkdirSync(path.dirname(baselinePath), { recursive: true });
    fs.writeFileSync(baselinePath, JSON.stringify({
        description: 'Exact pre-existing naming violations. Framework-required names are outside these rules.',
        exceptions: [],
        violations,
    }, null, 2) + '\n');
    console.log(`Wrote ${violations.length} naming baseline violations.`);
    process.exit(0);
}
const baseline = fs.existsSync(baselinePath) ? JSON.parse(fs.readFileSync(baselinePath, 'utf8')) : { violations: [] };
const known = new Set(baseline.violations);
const additions = violations.filter((item) => !known.has(item));
if (additions.length) {
    additions.forEach((item) => console.error(item));
    process.exit(1);
}
console.log(`Naming conventions passed: ${violations.length} known violations.`);

function inspect(root) {
    const violations = [];
    const phpRoot = path.join(root, 'app/Modules');
    if (fs.existsSync(phpRoot)) {
        for (const file of walk(phpRoot)) {
            if (!file.endsWith('.php')) continue;
            const relative = path.relative(root, file).replaceAll(path.sep, '/');
            const source = fs.readFileSync(file, 'utf8');
            const symbol = source.match(/(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/)?.[1];
            if (symbol && path.basename(file, '.php') !== symbol) violations.push({ file: relative, symbol, rule: 'php-file-symbol-mismatch' });
            if (symbol && /^(BaseService|CommonManager|Helpers)$/.test(symbol)) violations.push({ file: relative, symbol, rule: 'forbidden-generic-name' });
            if (symbol && /(?:^|[a-z])(AI|SRS|HTTP|ID)(?:[A-Z]|$)/.test(symbol)) violations.push({ file: relative, symbol, rule: 'acronym-style' });
        }
    }
    const spaRoot = path.join(root, 'resources/js/spa');
    if (fs.existsSync(spaRoot)) {
        for (const file of walk(spaRoot)) {
            const relative = path.relative(root, file).replaceAll(path.sep, '/');
            const basename = path.basename(file);
            if (file.endsWith('.vue') && !/^[A-Z][A-Za-z0-9]*\.vue$/.test(basename)) violations.push({ file: relative, symbol: basename, rule: 'vue-component-filename' });
            if (/\/domains\/[^/]+\/(api|model)\//.test(file) && file.endsWith('.ts')) {
                const valid = /(?:Api\.ts|\.(api|types|queryKeys|queries|mutations|events|serverState|store)\.ts)$/.test(basename) || /^use[A-Z][A-Za-z0-9]*\.ts$/.test(basename) || /^(index|types)\.ts$/.test(basename);
                if (!valid) violations.push({ file: relative, symbol: basename, rule: 'domain-file-convention' });
            }
        }
    }
    return violations;
}

function* walk(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const target = path.join(directory, entry.name);
        if (entry.isDirectory()) yield* walk(target);
        else yield target;
    }
}

function key(item) {
    return `${item.rule}|${item.file}|${item.symbol}`;
}
