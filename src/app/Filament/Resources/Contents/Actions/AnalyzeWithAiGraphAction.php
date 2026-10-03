<?php

namespace App\Filament\Resources\Contents\Actions;

use App\Modules\Ai\Interfaces\Jobs\RunAiAnalysisGraphJob;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Contracts\Ai\AiAnalysisRunConfig;
use App\Support\AiConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Task 8.4 — the beta, opt-in twin of `AnalyzeWithAiAction`: same schema,
 * same `AiAnalysisRunConfig` shape (so `AnalyzeNode` — which calls the same
 * `AiContentAnalysisService::analyze()` the live job calls — honors the
 * exact same options), same "create a fresh `AiAnalysisRun` row" pattern.
 * The only differences are the label/copy (explicitly marked "beta" and
 * "graph engine" so nobody confuses it with the live button) and dispatching
 * `RunAiAnalysisGraphJob` instead of `RunAiContentAnalysisJob`. Creating a
 * new run row (rather than reusing `latestAnalysisRun`) means this can run
 * side by side with the live path on the same `Content` without either one
 * disturbing the other — `Content::analysisRuns()` is a plain `hasMany`,
 * no unique constraint on `content_id`, exactly like clicking "Analyze with
 * AI" twice already produces two runs today.
 */
class AnalyzeWithAiGraphAction
{
    public static function make(?Content $pageRecord = null): Action
    {
        return Action::make('analyzeWithAiGraph')
            ->label('Analyze via graph engine (beta)')
            ->icon('heroicon-o-cpu-chip')
            ->color('gray')
            ->visible(fn (?Content $record = null): bool => AiConfig::isEnabled() && self::record($record, $pageRecord)->hasTranscript())
            ->requiresConfirmation()
            ->modalHeading('Run AI candidate extraction via the graph engine (beta)?')
            ->modalDescription('Same extraction as "Analyze with AI", run through the AiAnalysisGraph engine instead — creates its own analysis run, independent of any existing one, so you can compare results side by side. Does not change published data.')
            ->schema(fn (?Content $record = null) => self::schema(self::record($record, $pageRecord)))
            ->action(function (array $data, ?Content $record = null) use ($pageRecord): void {
                $content = self::record($record, $pageRecord);

                $manualExcludeWords = AiAnalysisRunConfig::fromArray(['exclude_words' => $data['exclude_words'] ?? []])->excludeWords;

                if ($data['exclude_known_words'] ?? false) {
                    $knownWords = Lexeme::query()
                        ->where('language', $content->language)
                        ->pluck('lemma')
                        ->all();
                    $data['exclude_words'] = array_values(array_unique([...$knownWords, ...$manualExcludeWords]));
                } else {
                    $data['exclude_words'] = $manualExcludeWords;
                }

                $config = AiAnalysisRunConfig::fromArray($data);

                $run = $content->analysisRuns()->create([
                    'status' => AiAnalysisRun::STATUS_PENDING,
                    'config' => $config->toArray(),
                ]);

                RunAiAnalysisGraphJob::dispatch($run->id);

                Notification::make()
                    ->title('AI analysis (graph engine, beta) started')
                    ->body('Candidates will appear below once the run completes or pauses for review.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private static function schema(Content $content): array
    {
        return [
            Select::make('target_level')
                ->label('Target level (CEFR)')
                ->helperText('Example sentences are written at this level of complexity, even for more advanced words.')
                ->options(array_combine(Content::CEFR_LEVELS, Content::CEFR_LEVELS))
                ->default($content->level)
                ->native(false),
            Select::make('translation_language')
                ->label('Translate into')
                ->helperText('Defaults to your profile setting.')
                ->options(AiAnalysisRunConfig::TRANSLATION_LANGUAGES)
                ->default(fn () => Auth::user()?->translation_language ?? config('ai.analysis.translation_language'))
                ->native(false),
            Checkbox::make('exclude_known_words')
                ->label('Exclude words already in the catalog')
                ->helperText('Looks up published lexemes for this language and tells the AI to skip them.')
                ->default(true),
            Textarea::make('exclude_words')
                ->label('Also exclude (manual)')
                ->helperText('One per line, or comma-separated. Combined with the catalog exclusion above.')
                ->rows(2),
            Select::make('exclude_grammar_rule_ids')
                ->label('Exclude grammar (already covered)')
                ->multiple()
                ->searchable()
                ->options(fn () => GrammarRule::query()->where('language', $content->language)->pluck('title', 'id')),
            Textarea::make('extra_instructions')
                ->label('Additional instructions for the AI')
                ->rows(2),
            Select::make('thoroughness')
                ->label('Thoroughness')
                ->options([
                    AiAnalysisRunConfig::THOROUGHNESS_FOCUSED => 'Focused (fewer, higher-confidence items)',
                    AiAnalysisRunConfig::THOROUGHNESS_THOROUGH => 'Thorough (find as much as possible)',
                ])
                ->default(AiAnalysisRunConfig::THOROUGHNESS_FOCUSED)
                ->native(false),
        ];
    }

    private static function record(?Content $record, ?Content $pageRecord): Content
    {
        if ($record) {
            return $record;
        }

        if ($pageRecord) {
            return $pageRecord;
        }

        throw new \LogicException('Analyze via graph engine action requires a content record.');
    }
}
