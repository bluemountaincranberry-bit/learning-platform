<?php

use App\Modules\Content\Infrastructure\Integrations\YoutubeOEmbedTitleFetcher;
use Illuminate\Support\Facades\Http;

test('fetches the title from the oEmbed endpoint for a youtube url', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(['title' => 'Some Video Title'], 200),
    ]);

    $title = (new YoutubeOEmbedTitleFetcher)->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($title)->toBe('Some Video Title');
});

test('returns null for a non-youtube url without making a request', function () {
    Http::fake();

    $title = (new YoutubeOEmbedTitleFetcher)->fetch('https://example.com/not-youtube');

    expect($title)->toBeNull();
    Http::assertNothingSent();
});

test('returns null when the oEmbed request fails', function () {
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(null, 404),
    ]);

    $title = (new YoutubeOEmbedTitleFetcher)->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($title)->toBeNull();
});

test('returns null when the request throws', function () {
    Http::fake(function (): never {
        throw new \Illuminate\Http\Client\ConnectionException('timed out');
    });

    $title = (new YoutubeOEmbedTitleFetcher)->fetch('https://youtu.be/dQw4w9WgXcQ');

    expect($title)->toBeNull();
});
