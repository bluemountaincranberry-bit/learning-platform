import { readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

/**
 * VIK-20: learner screens speak one interface language — English. Word and
 * example translations come from the API (content), never from source, so any
 * Cyrillic in learner SPA source outside comments is a UI string regression.
 */
const SPA_ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

// Admin-only screens are out of scope for the learner UI language.
const EXCLUDED_DIRS = ['pages/admin', 'domains/ai-builder'];

// Language endonyms in the native-language picker are names, not UI copy.
const ALLOWED_STRINGS = ["'Русский'", "'Українська'"];

const CYRILLIC = /\p{Script=Cyrillic}/u;

function sourceFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) {
            return name === '__tests__' || EXCLUDED_DIRS.includes(relative(SPA_ROOT, path)) ? [] : sourceFiles(path);
        }
        return /\.(vue|ts|js)$/.test(name) ? [path] : [];
    });
}

// Comments are developer notes, not UI. Only comments that start a line or
// follow whitespace are stripped, so `//` or `/*` inside strings and globs
// cannot hide real UI text.
function withoutComments(source: string): string {
    return source
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/^\s*\/\*[\s\S]*?\*\//gm, '')
        .replace(/(^|\s)\/\/.*$/gm, '$1');
}

function withoutAllowedStrings(source: string): string {
    return ALLOWED_STRINGS.reduce((text, allowed) => text.split(allowed).join(''), source);
}

describe('learner UI language', () => {
    it('has no Cyrillic UI strings in learner SPA source', () => {
        const offenders = sourceFiles(SPA_ROOT).flatMap((path) =>
            withoutAllowedStrings(withoutComments(readFileSync(path, 'utf8')))
                .split('\n')
                .filter((line) => CYRILLIC.test(line))
                .map((line) => `${relative(SPA_ROOT, path)}: ${line.trim()}`),
        );

        expect(offenders).toEqual([]);
    });
});
