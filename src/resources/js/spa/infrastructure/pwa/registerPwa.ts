import axios from 'axios';

async function sessionDigest(token: string | null): Promise<string | null> {
    if (!token) return null;
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token));
    return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/** Optional infrastructure: unsupported browsers and dev builds keep the normal SPA. */
export async function registerPwa(): Promise<(token: string | null) => Promise<void>> {
    const unavailable = async () => {};
    if (!import.meta.env.PROD || !window.isSecureContext || !('serviceWorker' in navigator)) return unavailable;
    try {
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' });
        await new Promise<void>((resolve, reject) => {
            if (navigator.serviceWorker.controller) return resolve();
            const timeout = window.setTimeout(() => { cleanup(); reject(new Error('PWA activation timed out')); }, 8000);
            const controlled = () => { cleanup(); resolve(); };
            const cleanup = () => { clearTimeout(timeout); navigator.serviceWorker.removeEventListener('controllerchange', controlled); };
            navigator.serviceWorker.addEventListener('controllerchange', controlled);
        });
        let pending = Promise.resolve();
        const synchronize = (token: string | null) => {
            pending = pending.catch(() => {}).then(async () => {
                const session = await sessionDigest(token);
                const worker = navigator.serviceWorker.controller;
                if (!worker) return;
                await new Promise<void>((resolve, reject) => {
                    const channel = new MessageChannel();
                    const timeout = window.setTimeout(() => { channel.port1.close(); reject(new Error('PWA session timed out')); }, 5000);
                    channel.port1.onmessage = () => { clearTimeout(timeout); channel.port1.close(); resolve(); };
                    worker.postMessage({ type: 'PWA_SESSION', session }, [channel.port2]);
                });
            });
            return pending;
        };
        // Gate requests until a logout/account change has reached the worker.
        axios.interceptors.request.use(async (config) => { await pending.catch(() => {}); return config; });
        window.addEventListener('online', () => { void registration.update().catch(() => {}); });
        return synchronize;
    } catch {
        return unavailable;
    }
}
