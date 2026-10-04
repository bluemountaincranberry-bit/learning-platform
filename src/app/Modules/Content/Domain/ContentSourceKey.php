<?php

namespace App\Modules\Content\Domain;

/**
 * Identity of a content's external source (VIK-16). Two contents with the
 * same key are the same material, whatever URL form was pasted:
 * `youtu.be/ID?si=…`, `youtube.com/watch?v=ID&list=…`, `/shorts/ID` all
 * become `youtube:ID`. Null when the source has no stable identity (typed
 * text, uploads, a URL that cannot be parsed).
 */
final class ContentSourceKey
{
    public static function for(string $type, ?string $sourceUrl): ?string
    {
        if ($type !== 'youtube' || $sourceUrl === null) {
            return null;
        }

        $videoId = self::youtubeVideoId($sourceUrl);

        return $videoId === null ? null : 'youtube:'.$videoId;
    }

    public static function youtubeVideoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if ($parts === false) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($host === 'youtu.be' || $host === 'www.youtu.be') {
            $id = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = rtrim($path, '/') === '/watch' ? ($query['v'] ?? '') : '';
            if (preg_match('~^/(?:embed|shorts|live|v)/([a-zA-Z0-9_-]{11})/?$~', $path, $matches)) {
                $id = $matches[1];
            }
        } else {
            return null;
        }

        return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $id) ? $id : null;
    }
}
