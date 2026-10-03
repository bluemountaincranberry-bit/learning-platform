/**
 * Extract the video id from a YouTube URL (watch/short/embed/youtu.be forms).
 * Returns null when the URL isn't a recognizable YouTube link.
 */
export function extractYoutubeVideoId(url?: string | null): string | null {
    if (!url) return null;

    try {
        const parsed = new URL(url);
        const host = parsed.hostname.replace(/^www\./, '');

        if (host === 'youtu.be') {
            return parsed.pathname.slice(1).split('/')[0] || null;
        }

        if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'music.youtube.com') {
            if (parsed.pathname === '/watch') {
                return parsed.searchParams.get('v');
            }
            const shortsMatch = parsed.pathname.match(/^\/(shorts|embed|live)\/([^/]+)/);
            if (shortsMatch) {
                return shortsMatch[2];
            }
        }

        return null;
    } catch {
        return null;
    }
}
