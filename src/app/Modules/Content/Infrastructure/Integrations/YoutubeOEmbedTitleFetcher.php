<?php

namespace App\Modules\Content\Infrastructure\Integrations;

use App\Contracts\VideoTitleFetcherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoutubeOEmbedTitleFetcher implements VideoTitleFetcherInterface
{
    public function fetch(string $sourceUrl): ?string
    {
        $url = trim($sourceUrl);

        if ($url === '' || ! $this->isYoutubeUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get('https://www.youtube.com/oembed', ['url' => $url, 'format' => 'json']);

            if (! $response->successful()) {
                return null;
            }

            $title = trim((string) $response->json('title'));

            return $title !== '' ? $title : null;
        } catch (\Throwable $e) {
            Log::info('YouTube oEmbed title lookup failed.', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }
    }

    private function isYoutubeUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return $host === 'youtu.be'
            || $host === 'youtube.com'
            || str_ends_with($host, '.youtube.com')
            || $host === 'youtube-nocookie.com'
            || str_ends_with($host, '.youtube-nocookie.com');
    }
}
