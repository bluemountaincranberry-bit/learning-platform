import json
import os
import re
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import parse_qs, urlparse

from youtube_transcript_api import YouTubeTranscriptApi


def video_id(value: str) -> str | None:
    parsed = urlparse(value)
    host = parsed.netloc.lower().split(":", 1)[0]
    if host == "youtu.be":
        return parsed.path.strip("/").split("/", 1)[0] or None
    if host.endswith("youtube.com"):
        if parsed.path == "/watch":
            return parse_qs(parsed.query).get("v", [None])[0]
        match = re.match(r"/(?:shorts|embed|live)/([^/]+)", parsed.path)
        return match.group(1) if match else None
    return None


def transcript(payload: dict) -> dict:
    identifier = video_id(str(payload.get("url", "")))
    if not identifier:
        raise ValueError("Invalid YouTube URL.")

    language = str(payload.get("language") or "").strip().lower()
    api = YouTubeTranscriptApi()
    fetched = api.fetch(identifier, languages=[language] if language else None)
    raw = fetched.to_raw_data()
    segments = [
        {
            "startMs": round(float(item.get("start", 0)) * 1000),
            "endMs": round((float(item.get("start", 0)) + float(item.get("duration", 0))) * 1000),
            "text": str(item.get("text", "")).strip(),
            "sourceKey": str(index),
        }
        for index, item in enumerate(raw)
        if str(item.get("text", "")).strip()
    ]
    if not segments:
        raise ValueError("Python youtube-transcript-api returned no captions.")

    return {
        "language": getattr(fetched, "language_code", None) or language or None,
        "source": "youtube-transcript-api-python",
        "segments": segments,
    }


class Handler(BaseHTTPRequestHandler):
    def send_json(self, status: int, body: dict) -> None:
        encoded = json.dumps(body).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)

    def do_GET(self) -> None:
        if self.path == "/health":
            self.send_json(200, {"status": "ok", "provider": "youtube-transcript-api-python"})
            return
        self.send_json(404, {"error": "Not found"})

    def do_POST(self) -> None:
        if self.path != "/transcript":
            self.send_json(404, {"error": "Not found"})
            return
        try:
            length = int(self.headers.get("Content-Length", "0"))
            payload = json.loads(self.rfile.read(length))
            self.send_json(200, transcript(payload))
        except Exception as error:
            self.send_json(502, {"error": str(error)})


if __name__ == "__main__":
    HTTPServer(("0.0.0.0", int(os.environ.get("PORT", "3001"))), Handler).serve_forever()
