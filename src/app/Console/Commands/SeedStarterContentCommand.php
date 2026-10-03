<?php

namespace App\Console\Commands;

use App\Modules\Content\Domain\Events\ContentProcessingRequested;
use App\Modules\Content\Domain\Models\Content;
use App\Support\AiConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SeedStarterContentCommand extends Command
{
    protected $signature = 'content:seed-starter {--dry-run : Show missing talks without writing or calling providers}';

    protected $description = 'Import the configured starter TED catalog through the normal ingestion pipeline';

    public function handle(): int
    {
        $talks = config('starter-content.talks');
        $validator = Validator::make(['talks' => $talks], [
            'talks' => ['required', 'array', 'min:1'],
            'talks.*' => ['required', 'array'],
            'talks.*.url' => ['required', 'url'],
            'talks.*.title' => ['required', 'string', 'max:255'],
            'talks.*.language' => ['required', 'string', 'regex:/^[a-z]{2}$/'],
            'talks.*.level' => ['required', Rule::in(Content::CEFR_LEVELS)],
        ]);
        if ($validator->fails()) {
            $this->error('Invalid starter-content configuration: '.$validator->errors()->first());

            return self::FAILURE;
        }
        foreach ($talks as $talk) {
            if ($this->videoId($talk['url']) === null) {
                $this->error('Every starter URL must identify a YouTube video.');

                return self::FAILURE;
            }
        }

        $lock = Cache::lock('content:seed-starter', 3600);
        if (! $lock->get()) {
            $this->error('Another starter import is running. Try again after it finishes.');

            return self::FAILURE;
        }

        try {
            $existing = Content::query()->where('type', 'youtube')->pluck('source_url')
                ->map(fn (?string $url): ?string => $this->videoId($url ?? ''))->filter()->flip()->all();
            $missing = [];
            $skipped = 0;
            foreach ($talks as $talk) {
                $id = $this->videoId($talk['url']);
                if (isset($existing[$id])) {
                    $skipped++;

                    continue;
                }
                $existing[$id] = true;
                $missing[] = $talk;
            }

            if ($this->option('dry-run')) {
                foreach ($missing as $talk) {
                    $this->line('Would import: '.$talk['title']);
                }
                $this->info('Would create: '.count($missing).'; skipped: '.$skipped);

                return self::SUCCESS;
            }
            if ($missing !== [] && ! AiConfig::isEnabled()) {
                $this->error('Enable AI_FEATURE_ENABLED and configure the existing AI provider before importing starter content.');

                return self::FAILURE;
            }

            $failed = false;
            foreach ($missing as $talk) {
                $content = Content::query()->create([
                    'type' => 'youtube', 'origin' => 'curated', 'status' => 'pending',
                    'source_url' => 'https://www.youtube.com/watch?v='.$this->videoId($talk['url']),
                    'title' => $talk['title'], 'language' => $talk['language'], 'level' => $talk['level'],
                ]);
                try {
                    ContentProcessingRequested::dispatch($content->id);
                    $this->line('Submitted #'.$content->id.': '.$content->title);
                } catch (\Throwable $e) {
                    report($e);
                    $failed = true;
                    $this->error('Processing request failed for #'.$content->id.'. Inspect its processing status and application logs before retrying in Admin.');
                }
            }
            $this->info('Created: '.count($missing).'; skipped: '.$skipped);
            $this->line('Drain the ingestion and AI queues; ready status alone does not guarantee words and grammar.');

            return $failed ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function videoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = $path === '/watch' ? ($query['v'] ?? '') : '';
            if (preg_match('~^/(?:embed|shorts|live)/([a-zA-Z0-9_-]{11})/?$~', $path, $matches)) {
                $id = $matches[1];
            }
        } else {
            return null;
        }

        return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $id) ? $id : null;
    }
}
