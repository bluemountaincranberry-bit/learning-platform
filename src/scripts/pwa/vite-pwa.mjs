import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

/** Emit a worker at /sw.js (root scope) and a public, credential-free SPA shell. */
export function lessonPwa() {
    let root;
    let worker;
    return {
        name: 'lesson-pwa',
        apply: 'build',
        configResolved(config) { root = config.root; },
        generateBundle(_options, bundle) {
            const entry = Object.values(bundle).find((asset) => asset.type === 'chunk' && asset.isEntry && asset.facadeModuleId?.endsWith('/spa/main.ts'));
            if (!entry) throw new Error('PWA: SPA entry missing');
            const styles = Object.keys(bundle).filter((name) => name.endsWith('.css'));
            const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0f172a"><link rel="manifest" href="/manifest.webmanifest"><link rel="apple-touch-icon" href="/icons/apple-touch-icon.png"><meta name="apple-mobile-web-app-capable" content="yes"><title>Learning App</title>${styles.map((name) => `<link rel="stylesheet" href="/build/${name}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.fileName}"></script></body></html>`;
            this.emitFile({ type: 'asset', fileName: 'offline.html', source: html });
            const assets = Object.keys(bundle).filter((name) => /\.(js|css|woff2?|png|svg)$/.test(name)).map((name) => `/build/${name}`);
            assets.push('/build/offline.html', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/apple-touch-icon.png');
            const source = readFileSync(resolve(root, 'resources/js/spa/infrastructure/pwa/service-worker.js'), 'utf8');
            const fingerprint = createHash('sha256').update(source).update(html).update(assets.join('\n'));
            for (const asset of assets.filter((path) => !path.startsWith('/build/'))) fingerprint.update(readFileSync(resolve(root, 'public', asset.slice(1))));
            const version = fingerprint.digest('hex').slice(0, 16);
            worker = source.replace('__PWA_VERSION__', JSON.stringify(version)).replace('__PWA_ASSETS__', JSON.stringify(assets));
        },
        writeBundle() { writeFileSync(resolve(root, 'public/sw.js'), worker); },
    };
}
