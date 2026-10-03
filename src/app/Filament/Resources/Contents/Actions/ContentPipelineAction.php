<?php

namespace App\Filament\Resources\Contents\Actions;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ContentPipelineAction
{
    public static function make(string $name = 'submitForProcessing', ?Content $pageRecord = null): Action
    {
        return Action::make($name)
            ->label(fn (?Content $record = null): string => self::record($record, $pageRecord)->status === 'failed' ? 'Retry pipeline' : 'Start pipeline')
            ->icon('heroicon-o-play')
            ->color('primary')
            ->tooltip('For YouTube: fetches the transcript. For everything else: tokenizes the text into words. Runs in the background.')
            ->visible(fn (?Content $record = null): bool => self::canStart(self::record($record, $pageRecord)))
            ->requiresConfirmation()
            ->modalHeading(fn (?Content $record = null): string => self::record($record, $pageRecord)->status === 'failed' ? 'Retry content pipeline?' : 'Start content pipeline?')
            ->modalDescription(fn (?Content $record = null): string => self::record($record, $pageRecord)->type === 'youtube' && trim((string) self::record($record, $pageRecord)->source_text) === ''
                ? 'Fetches the transcript from YouTube, then tokenizes it into words so you can run AI analysis. Runs in the background via Redis and Horizon.'
                : 'Tokenizes the text into words so you can run AI analysis. Runs in the background via Redis and Horizon.')
            ->action(function (?Content $record = null) use ($pageRecord): void {
                $content = self::record($record, $pageRecord);
                $wasRetry = $content->status === 'failed';
                $result = app(ContentProcessingOrchestratorInterface::class)->request($content);

                if (! $result->accepted) {
                    Notification::make()
                        ->title($result->reason ?? 'Unable to start pipeline')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($wasRetry ? 'Pipeline retry started' : 'Pipeline started')
                    ->body('Track the job in Horizon and the content status in this admin screen.')
                    ->success()
                    ->send();
            });
    }

    private static function record(?Content $record, ?Content $pageRecord): Content
    {
        if ($record) {
            return $record;
        }

        if ($pageRecord) {
            return $pageRecord;
        }

        throw new \LogicException('Content pipeline action requires a content record.');
    }

    private static function canStart(Content $content): bool
    {
        return in_array($content->status, ['draft', 'pending', 'failed'], true)
            || ($content->status === 'ready' && $content->analysis_stale_at !== null);
    }
}
