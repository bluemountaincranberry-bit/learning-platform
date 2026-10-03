<?php

use App\Exceptions\SubtitleExtractionException;
use App\Modules\Content\Infrastructure\Subtitles\SrtVttSubtitleTextExtractor;

function writeSubtitleFile(string $contents, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'subtest').'.'.$extension;
    file_put_contents($path, $contents);

    return $path;
}

test('extracts dialogue from a .srt file', function () {
    $srt = <<<SRT
1
00:00:01,000 --> 00:00:04,000
Hello there.

2
00:00:04,500 --> 00:00:07,000
How are you
doing today?

SRT;

    $path = writeSubtitleFile($srt, 'srt');

    $text = (new SrtVttSubtitleTextExtractor)->extractFromPath($path);

    expect($text)->toBe("Hello there.\nHow are you doing today?");

    unlink($path);
});

test('extracts dialogue from a .vtt file and strips cue ids, header and tags', function () {
    $vtt = <<<VTT
WEBVTT

cue-1
00:00:01.000 --> 00:00:04.000 align:middle
<b>Hello</b> there.

00:00:04.500 --> 00:00:07.000
How are you doing today?

VTT;

    $path = writeSubtitleFile($vtt, 'vtt');

    $text = (new SrtVttSubtitleTextExtractor)->extractFromPath($path);

    expect($text)->toBe("Hello there.\nHow are you doing today?");

    unlink($path);
});

test('collapses consecutive duplicate cues from overlapping srt exports', function () {
    $srt = <<<SRT
1
00:00:01,000 --> 00:00:03,000
Same line.

2
00:00:02,500 --> 00:00:05,000
Same line.

3
00:00:05,000 --> 00:00:08,000
Different line.

SRT;

    $path = writeSubtitleFile($srt, 'srt');

    $text = (new SrtVttSubtitleTextExtractor)->extractFromPath($path);

    expect($text)->toBe("Same line.\nDifferent line.");

    unlink($path);
});

test('throws for an unsupported extension', function () {
    $path = writeSubtitleFile('whatever', 'txt');

    (new SrtVttSubtitleTextExtractor)->extractFromPath($path);

    unlink($path);
})->throws(SubtitleExtractionException::class, 'Unsupported subtitle format');

test('throws when the file does not exist', function () {
    (new SrtVttSubtitleTextExtractor)->extractFromPath('/tmp/does-not-exist-'.uniqid().'.srt');
})->throws(SubtitleExtractionException::class, 'not found');

test('throws when the file has no readable dialogue', function () {
    $path = writeSubtitleFile("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n", 'vtt');

    (new SrtVttSubtitleTextExtractor)->extractFromPath($path);

    unlink($path);
})->throws(SubtitleExtractionException::class, 'no readable dialogue');
