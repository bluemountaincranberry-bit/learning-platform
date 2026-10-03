<?php

namespace App\Filament\Resources\Contents\RelationManagers;

use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class GrammarCandidatesRelationManager extends RelationManager
{
    protected static string $relationship = 'grammarCandidates';

    protected static ?string $title = 'AI grammar candidates';

    protected static ?string $modelLabel = 'grammar candidate';

    protected static ?string $pluralModelLabel = 'grammar candidates';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextInputColumn::make('title')
                    ->rules(['required'])
                    ->afterStateUpdated(fn (ContentGrammarCandidate $record) => $this->markEdited($record)),
                TextInputColumn::make('summary')
                    ->placeholder('—')
                    ->afterStateUpdated(fn (ContentGrammarCandidate $record) => $this->markEdited($record)),
                TextColumn::make('example')->wrap()->limit(80)->toggleable(),
                TextInputColumn::make('example_translation')
                    ->label('Example translation')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->afterStateUpdated(fn (ContentGrammarCandidate $record) => $this->markEdited($record)),
                TextInputColumn::make('note')
                    ->label('Note')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->afterStateUpdated(fn (ContentGrammarCandidate $record) => $this->markEdited($record)),
                TextColumn::make('confidence')->numeric(2)->toggleable(),
                TextColumn::make('matchedGrammarRule.title')
                    ->label('Matched rule')
                    ->placeholder('No match')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                TextColumn::make('match_score')->label('Match score')->numeric(3)->toggleable(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'accepted', 'applied' => 'success',
                        'rejected' => 'danger',
                        'edited' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime()->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('accept')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ContentGrammarCandidate $record): bool => in_array($record->status, [ContentGrammarCandidate::STATUS_PENDING, ContentGrammarCandidate::STATUS_EDITED], true))
                    ->action(fn (ContentGrammarCandidate $record) => $record->update(['status' => ContentGrammarCandidate::STATUS_ACCEPTED])),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ContentGrammarCandidate $record): bool => in_array($record->status, [ContentGrammarCandidate::STATUS_PENDING, ContentGrammarCandidate::STATUS_EDITED], true))
                    ->action(fn (ContentGrammarCandidate $record) => $record->update(['status' => ContentGrammarCandidate::STATUS_REJECTED])),
                Action::make('ignoreMatch')
                    ->label('Ignore match')
                    ->icon('heroicon-o-link-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Clears the suggested match so Apply will create a new grammar rule instead of linking to the existing one.')
                    ->visible(fn (ContentGrammarCandidate $record): bool => $record->matched_grammar_rule_id !== null)
                    ->action(fn (ContentGrammarCandidate $record) => $record->update([
                        'matched_grammar_rule_id' => null,
                        'status' => ContentGrammarCandidate::STATUS_EDITED,
                    ])),
            ])
            ->headerActions([
                Action::make('acceptAboveThreshold')
                    ->label('Accept all above confidence')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->schema([
                        TextInput::make('threshold')
                            ->label('Minimum confidence (0-1)')
                            ->numeric()
                            ->default(0.8)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $run = $this->getOwnerRecord()->latestAnalysisRun;
                        if (! $run) {
                            Notification::make()->title('No analysis run yet')->warning()->send();

                            return;
                        }

                        $count = $run->grammarCandidates()
                            ->where('status', ContentGrammarCandidate::STATUS_PENDING)
                            ->where('confidence', '>=', (float) $data['threshold'])
                            ->update(['status' => ContentGrammarCandidate::STATUS_ACCEPTED]);

                        Notification::make()->title("Accepted {$count} candidate(s)")->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('acceptSelected')
                    ->label('Accept selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn (Collection $records) => $records->each(fn (ContentGrammarCandidate $r) => $r->update(['status' => ContentGrammarCandidate::STATUS_ACCEPTED]))),
                BulkAction::make('rejectSelected')
                    ->label('Reject selected')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (ContentGrammarCandidate $r) => $r->update(['status' => ContentGrammarCandidate::STATUS_REJECTED]))),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    private function markEdited(ContentGrammarCandidate $record): void
    {
        $record->update(['status' => ContentGrammarCandidate::STATUS_EDITED]);
    }
}
