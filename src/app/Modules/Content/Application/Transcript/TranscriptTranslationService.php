<?php

namespace App\Modules\Content\Application\Transcript;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\TranscriptSegmentTranslation;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Support\Facades\DB;

class TranscriptTranslationService
{
    public function __construct(private readonly AiJsonClient $client) {}

    /** @return array<int, string> keyed by transcript segment id */
    public function getCached(Content $content, string $language): array
    {
        return TranscriptSegmentTranslation::query()
            ->whereIn('transcript_segment_id', $content->transcriptSegments()->pluck('id'))
            ->where('language', trim($language))
            ->pluck('text', 'transcript_segment_id')
            ->all();
    }

    public function hasMissing(Content $content, string $language): bool
    {
        return count($this->getCached($content, $language)) < $content->transcriptSegments()->count();
    }

    /** @return array<int, string> keyed by transcript segment id */
    public function getOrCreate(Content $content, string $language): array
    {
        $language = trim($language);
        if ($language === '') {
            return [];
        }

        $segments = $content->transcriptSegments()->get();
        $existing = TranscriptSegmentTranslation::query()
            ->whereIn('transcript_segment_id', $segments->pluck('id'))
            ->where('language', $language)
            ->pluck('text', 'transcript_segment_id')
            ->all();

        $missing = $segments->filter(fn ($segment): bool => ! array_key_exists($segment->id, $existing));
        if ($missing->isEmpty()) {
            return $existing;
        }

        foreach ($missing->chunk(50) as $chunk) {
            $items = $chunk->map(fn ($segment): array => ['id' => $segment->id, 'text' => $segment->text])->values()->all();
            $result = $this->client->completeJson(
                systemPrompt: 'Translate transcript subtitle lines into the requested learner language. Preserve line meaning, keep the same ids, and return only JSON.',
                userPrompt: json_encode([
                    'target_language' => $language,
                    'segments' => $items,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                schema: [
                    'translations' => [
                        'type' => 'array',
                        'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'text' => ['type' => 'string']]],
                    ],
                ],
            );

            $translations = collect($result['translations'] ?? [])
                ->filter(fn ($item): bool => is_array($item) && isset($item['id'], $item['text']))
                ->mapWithKeys(fn (array $item): array => [(int) $item['id'] => trim((string) $item['text'])])
                ->filter(fn (string $text): bool => $text !== '');

            DB::transaction(function () use ($translations, $language): void {
                foreach ($translations as $segmentId => $text) {
                    TranscriptSegmentTranslation::query()->updateOrCreate(
                        ['transcript_segment_id' => $segmentId, 'language' => $language],
                        ['text' => $text, 'source' => 'ai'],
                    );
                }
            });

            $existing += $translations->all();
        }

        return $existing;
    }
}
