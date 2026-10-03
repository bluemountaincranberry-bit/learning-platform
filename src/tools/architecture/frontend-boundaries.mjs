import ts from 'typescript';
import { parse } from '@vue/compiler-sfc';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export function frontendViolations(file, code) {
    const scripts = file.endsWith('.vue') ? (() => {
        const { descriptor, errors } = parse(code, { filename: file });
        if (errors.length) throw new Error(`${file}: ${errors.join(', ')}`);
        return [descriptor.script?.content, descriptor.scriptSetup?.content].filter(Boolean);
    })() : [code];
    const violations = [];
    const add = (dependency, rule) => violations.push({ file, dependency, rule });
    const owner = file.match(/^domains\/([^/]+)\//)?.[1];
    function inspectImport(specifier) {
        const target = specifier.startsWith('@/spa/') ? specifier.slice(6) : specifier.startsWith('.') ? path.posix.normalize(path.posix.join(path.posix.dirname(file), specifier)) : specifier;
        const domain = target.match(/^domains\/([^/]+)(?:\/(.*))?$/);
        if (domain && domain[1] !== owner && domain[2] && !/^index(?:\.ts)?$/.test(domain[2])) add(target, 'ts-private-domain');
        if (/^(shared|infrastructure)\//.test(file) && /^(domains|pages|widgets)\//.test(target)) add(target, 'ts-shared-business');
        if (/^pages\//.test(file) && (/^(api|infrastructure\/http)(\/|$)/.test(target) || ['axios', 'ky'].includes(target))) add(target, 'ts-page-http');
        if (/^(domains|pages|widgets)\//.test(file) && /^(api|composables)\//.test(target)) add(target, 'ts-legacy-root');
    }
    for (const script of scripts) {
        const source = ts.createSourceFile(file, script, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
        if (source.parseDiagnostics.length) throw new Error(`${file}: TypeScript parse error`);
        function visit(node) {
            if ((ts.isImportDeclaration(node) || ts.isExportDeclaration(node)) && node.moduleSpecifier && ts.isStringLiteral(node.moduleSpecifier)) inspectImport(node.moduleSpecifier.text);
            if (ts.isImportTypeNode(node) && ts.isLiteralTypeNode(node.argument) && ts.isStringLiteral(node.argument.literal)) inspectImport(node.argument.literal.text);
            if (ts.isCallExpression(node)) {
                if ((node.expression.kind === ts.SyntaxKind.ImportKeyword || node.expression.getText(source) === 'require') && node.arguments[0] && ts.isStringLiteral(node.arguments[0])) inspectImport(node.arguments[0].text);
                if (/^pages\//.test(file) && /^(fetch|globalThis\.fetch|window\.fetch|new XMLHttpRequest)$/.test(node.expression.getText(source))) add(node.expression.getText(source), 'ts-page-http');
            }
            if (/^pages\//.test(file) && ts.isNewExpression(node) && node.expression.getText(source) === 'XMLHttpRequest') add('XMLHttpRequest', 'ts-page-http');
            ts.forEachChild(node, visit);
        }
        visit(source);
    }
    return [...new Map(violations.map(value => [JSON.stringify(value), value])).values()];
}

const directory = path.dirname(fileURLToPath(import.meta.url));
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const root = path.resolve(directory, '../../resources/js/spa');
    const violations = [];
    function walk(folder) {
        for (const entry of fs.readdirSync(folder, { withFileTypes: true })) {
            const full = path.join(folder, entry.name);
            if (entry.isDirectory()) walk(full);
            else if (/\.(ts|vue)$/.test(entry.name)) violations.push(...frontendViolations(path.relative(root, full), fs.readFileSync(full, 'utf8')));
        }
    }
    walk(root);
    violations.sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b)));
    if (process.argv.includes('--inventory')) console.log(JSON.stringify(violations, null, 2));
    else {
        const baseline = new Set(JSON.parse(fs.readFileSync(path.join(directory, 'frontend-baseline.json'), 'utf8')).map(value => JSON.stringify(value)));
        const added = violations.filter(value => !baseline.has(JSON.stringify(value)));
        for (const value of added) console.error(`${value.file} | ${value.dependency} | ${value.rule}`);
        console.log(`${added.length} new frontend boundary violations; ${violations.length} existing violations.`);
        process.exitCode = added.length ? 1 : 0;
    }
}
