<?php

namespace App\Filament\Resources\GrammarRules\Actions;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\AiGrammarExerciseService;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Support\AiConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Generates draft exercises via AI and persists them immediately (unlike
 * AiDraftGrammarContentAction, which only fills the live form) — review and
 * publish/edit/delete individually happens in ExercisesRelationManager below.
 */
class GenerateGrammarExercisesAction
{
    public static function make(GrammarRule $record): Action
    {
        return Action::make('generateGrammarExercises')
            ->label('AI: Generate exercises')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->visible(fn (): bool => AiConfig::isEnabled())
            ->schema([
                TextInput::make('count')
                    ->label('How many')
                    ->numeric()
                    ->default((int) config('ai.exercises.default_count', 5))
                    ->minValue(1)
                    ->maxValue((int) config('ai.exercises.max_count', 10))
                    ->required(),
                Textarea::make('instruction')
                    ->label('Instruction (optional)')
                    ->helperText('E.g. "focus on past tense forms" or "make the multiple-choice options trickier".')
                    ->rows(2),
            ])
            ->action(function (array $data) use ($record): void {
                try {
                    $created = app(AiGrammarExerciseService::class)->generate(
                        $record->id,
                        (int) $data['count'],
                        filled($data['instruction'] ?? null) ? $data['instruction'] : null
                    );
                } catch (AiClientException $e) {
                    Notification::make()->title('AI service unavailable')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($created.' exercise(s) drafted — review and publish below')
                    ->success()
                    ->send();
            });
    }
}
