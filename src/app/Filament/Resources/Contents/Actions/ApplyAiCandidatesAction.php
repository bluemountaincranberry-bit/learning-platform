<?php

namespace App\Filament\Resources\Contents\Actions;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Application\Contracts\CandidateApplicationInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ApplyAiCandidatesAction
{
    public static function make(?Content $pageRecord = null): Action
    {
        return Action::make('applyAiCandidates')
            ->label('Apply approved AI candidates')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->visible(fn (?Content $record = null): bool => self::hasAcceptedCandidates(self::record($record, $pageRecord)))
            ->requiresConfirmation()
            ->modalHeading('Apply approved candidates?')
            ->modalDescription('Creates or links canonical lexemes and grammar rules from candidates marked "accepted" in the latest analysis run. Rejected and still-pending candidates are left untouched.')
            ->action(function (?Content $record = null) use ($pageRecord): void {
                $content = self::record($record, $pageRecord);
                $run = $content->latestAnalysisRun;

                if (! $run) {
                    Notification::make()->title('No analysis run to apply')->warning()->send();

                    return;
                }

                $result = app(CandidateApplicationInterface::class)->apply($run);

                if ($result['lexemes'] === 0 && $result['grammar'] === 0) {
                    Notification::make()
                        ->title('Nothing to apply')
                        ->body('No accepted candidates were found in the latest analysis run.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Candidates applied')
                    ->body("Applied {$result['lexemes']} lexeme candidate(s) and {$result['grammar']} grammar candidate(s) to the catalog.")
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

        throw new \LogicException('Apply AI candidates action requires a content record.');
    }

    private static function hasAcceptedCandidates(Content $content): bool
    {
        $run = $content->latestAnalysisRun;

        if (! $run) {
            return false;
        }

        return $run->lexemeCandidates()->where('status', ContentLexemeCandidate::STATUS_ACCEPTED)->exists()
            || $run->grammarCandidates()->where('status', ContentGrammarCandidate::STATUS_ACCEPTED)->exists();
    }
}
