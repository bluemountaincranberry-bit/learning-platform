# Learning App YouTube subtitles extension

This MV3 extension reads the caption track from the currently open YouTube tab and sends only the transcript to Learning App. YouTube cookies stay in Chrome and are never uploaded.

## Install locally

1. Open `chrome://extensions`.
2. Enable **Developer mode**.
3. Choose **Load unpacked** and select this directory.
4. Open a YouTube video, open the extension popup, enter the Learning App URL and a personal API token, then click **Import current video**.
5. Approve Chrome's optional permission for the Learning App domain when prompted. This is requested only for the URL entered in the popup.

The token is stored in Chrome extension storage. Use a dedicated/revocable token for local development.

If direct caption data is empty, the extension opens YouTube's localized
transcript panel and reads its rendered segments. The video must be open in a
normal signed-in YouTube tab; cookies are never sent to Learning App.
If YouTube exposes caption metadata but does not expose the transcript body,
the extension submits the URL to the existing server ingestion fallback.

## Debugging

On the YouTube tab press `F12` → **Console**, then run the import again. Look
for `[Learning App YouTube]`. The log includes the selected path, caption
response statuses/sizes, transcript button labels, and number of extracted
segments. It never logs cookies or the API token.
