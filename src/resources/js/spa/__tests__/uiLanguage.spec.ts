import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * VIK-20: learner screens speak one interface language — English. Word and
 * example translations come from the API (content), never from source, so any
 * Cyrillic in learner SPA source outside comments is a UI string regression.
 */
const SPA_ROOT = join(__dirname, '..');

// Admin-only screens are out of scope for the learner UI language.
const EXCLUDED_DIRS = ['pages/admin', 'domains/ai-builder'];

// Language endonyms ("Русский", "Українська") are names, not UI copy.
const ALLOWED_FILES = ['composables/useProfileOptions.ts'];

const CYRILLIC = /[Ѐ-ӿ]/;

function sourceFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) {
            return name === '__tests__' || EXCLUDED_DIRS.includes(relative(SPA_ROOT, path)) ? [] : sourceFiles(path);
        }
        return /\.(vue|ts)$/.test(name) ? [path] : [];
    });
}

function withoutComments(source: string): string {
    return source
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
}

describe('learner UI language', () => {
    it('has no Cyrillic UI strings in learner SPA source', () => {
        const offenders = sourceFiles(SPA_ROOT)
            .filter((path) => !ALLOWED_FILES.includes(relative(SPA_ROOT, path)))
            .flatMap((path) =>
                withoutComments(readFileSync(path, 'utf8'))
                    .split('\n')
                    .filter((line) => CYRILLIC.test(line))
                    .map((line) => `${relative(SPA_ROOT, path)}: ${line.trim()}`),
            );

        expect(offenders).toEqual([]);
    });
});
