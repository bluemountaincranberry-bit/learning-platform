/**
 * Payload for POST /api/content/submit-youtube.
 */
export interface SubmitYoutubePayload {
    /** Omitted/empty to let the backend auto-fill it from the YouTube video (oEmbed lookup). */
    title?: string;
    source_url: string;
    language: string;
    level?: string | null;
}
