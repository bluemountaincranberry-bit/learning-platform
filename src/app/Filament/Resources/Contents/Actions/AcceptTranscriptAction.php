<?php

namespace App\Filament\Resources\Contents\Actions;

use App\Modules\Content\Actions\AcceptTranscript;
use App\Modules\Content\Domain\Models\Content;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AcceptTranscriptAction
{
    public static function make(string $name = 'acceptTranscript', ?Content $pageRecord = null): Action
    {
        return Action::make($name)
            ->label('Accept transcript')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (?Content $record = null): bool => self::record($record, $pageRecord)->hasTranscript())
            ->disabled(fn (?Content $record = null): bool => self::record($record, $pageRecord)->hasTranscriptAcceptance())
            ->requiresConfirmation()
            ->modalHeading('Accept transcript?')
            ->modalDescription('This marks the current transcript text as approved for downstream processing.')
            ->action(function (?Content $record = null) use ($pageRecord): void {
                $content = self::record($record, $pageRecord);

                if (! $content->hasTranscript()) {
                    Notification::make()
                        ->title('Transcript is empty')
                        ->warning()
                        ->send();

                    return;
                }

                app(AcceptTranscript::class)->execute($content, auth()->id());

                Notification::make()
                    ->title('Transcript accepted')
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

        throw new \LogicException('Accept transcript action requires a content record.');
    }
}
