<?php

namespace App\Filament\Resources\Contents\Actions;

use App\Modules\Ai\Application\AiContentResetService;
use App\Modules\Content\Domain\Models\Content;
use App\Support\AiConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class ResetAiAnalysisAction
{
    public static function make(?Content $pageRecord = null): Action
    {
        return Action::make('resetAiAnalysis')
            ->label('Reset AI and rerun')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->visible(fn (?Content $record = null): bool => AiConfig::isEnabled() && self::record($record, $pageRecord)->hasTranscript())
            ->requiresConfirmation()
            ->modalHeading('Reset AI analysis and rerun?')
            ->modalDescription('Choose exactly what should be removed. Full content reset deletes content words and their learning progress.')
            ->schema([
                Select::make('mode')
                    ->label('Reset scope')
                    ->required()
                    ->native(false)
                    ->options([
                        AiContentResetService::MODE_HISTORY => 'History only — preserve published results',
                        AiContentResetService::MODE_AI_RESULTS => 'AI results for this content — preserve learning progress where possible',
                        AiContentResetService::MODE_FULL_CONTENT => 'Full content reset — delete content words and progress',
                    ])
                    ->default(AiContentResetService::MODE_HISTORY)
                    ->helperText('The first option removes runs/candidates only. The second removes unlearned AI-origin content words. The third reruns tokenization and AI from the transcript.'),
            ])
            ->action(function (array $data, ?Content $record = null) use ($pageRecord): void {
                try {
                    $result = app(AiContentResetService::class)->resetAndRerun(
                        self::record($record, $pageRecord)->id,
                        (string) $data['mode'],
                    );
                } catch (\Throwable $e) {
                    Notification::make()->title('AI reset was not started')->body($e->getMessage())->danger()->send();

                    return;
                }

                $body = "Deleted {$result['deleted_runs']} run(s).";
                if ($result['deleted_content_lexemes'] > 0) {
                    $body .= " Removed {$result['deleted_content_lexemes']} AI content word(s).";
                }
                if ($result['preserved_content_lexemes'] > 0) {
                    $body .= " Preserved {$result['preserved_content_lexemes']} learned AI word(s) with progress.";
                }

                Notification::make()->title('AI rerun started')->body($body)->success()->send();
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
        throw new \LogicException('AI reset action requires a content record.');
    }
}
