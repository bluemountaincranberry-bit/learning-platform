<?php

namespace App\Filament\Resources\Contents\RelationManagers;

use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class LexemeCandidatesRelationManager extends RelationManager
{
    protected static string $relationship = 'lexemeCandidates';

    protected static ?string $title = 'AI lexeme candidates';

    protected static ?string $modelLabel = 'lexeme candidate';

    protected static ?string $pluralModelLabel = 'lexeme candidates';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')->searchable(),
                SelectColumn::make('type')
                    ->options(array_combine(ContentLexemeCandidate::TYPES, ContentLexemeCandidate::TYPES))
                    ->afterStateUpdated(fn (ContentLexemeCandidate $record) => $this->markEdited($record)),
                TextColumn::make('level')->badge()->placeholder('—')->toggleable(),
                TextColumn::make('frequency')->label('Freq.')->numeric()->toggleable(),
                TextInputColumn::make('translation')
                    ->placeholder('—')
                    ->afterStateUpdated(fn (ContentLexemeCandidate $record) => $this->markEdited($record)),
                TextColumn::make('example')->wrap()->limit(80)->toggleable(),
                TextInputColumn::make('example_translation')
                    ->label('Example translation')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->afterStateUpdated(fn (ContentLexemeCandidate $record) => $this->markEdited($record)),
                TextInputColumn::make('note')
                    ->label('Note')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->afterStateUpdated(fn (ContentLexemeCandidate $record) => $this->markEdited($record)),
                TextColumn::make('confidence')->numeric(2)->toggleable(),
                TextColumn::make('matchedLexeme.lemma')
                    ->label('Matched lexeme')
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
                    ->visible(fn (ContentLexemeCandidate $record): bool => in_array($record->status, [ContentLexemeCandidate::STATUS_PENDING, ContentLexemeCandidate::STATUS_EDITED], true))
                    ->action(fn (ContentLexemeCandidate $record) => $record->update(['status' => ContentLexemeCandidate::STATUS_ACCEPTED])),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ContentLexemeCandidate $record): bool => in_array($record->status, [ContentLexemeCandidate::STATUS_PENDING, ContentLexemeCandidate::STATUS_EDITED], true))
                    ->action(fn (ContentLexemeCandidate $record) => $record->update(['status' => ContentLexemeCandidate::STATUS_REJECTED])),
                Action::make('ignoreMatch')
                    ->label('Ignore match')
                    ->icon('heroicon-o-link-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Clears the suggested match so Apply will create a new canonical lexeme instead of linking to the existing one.')
                    ->visible(fn (ContentLexemeCandidate $record): bool => $record->matched_lexeme_id !== null)
                    ->action(fn (ContentLexemeCandidate $record) => $record->update([
                        'matched_lexeme_id' => null,
                        'status' => ContentLexemeCandidate::STATUS_EDITED,
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

                        $count = $run->lexemeCandidates()
                            ->where('status', ContentLexemeCandidate::STATUS_PENDING)
                            ->where('confidence', '>=', (float) $data['threshold'])
                            ->update(['status' => ContentLexemeCandidate::STATUS_ACCEPTED]);

                        Notification::make()->title("Accepted {$count} candidate(s)")->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('acceptSelected')
                    ->label('Accept selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn (Collection $records) => $records->each(fn (ContentLexemeCandidate $r) => $r->update(['status' => ContentLexemeCandidate::STATUS_ACCEPTED]))),
                BulkAction::make('rejectSelected')
                    ->label('Reject selected')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (ContentLexemeCandidate $r) => $r->update(['status' => ContentLexemeCandidate::STATUS_REJECTED]))),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    private function markEdited(ContentLexemeCandidate $record): void
    {
        $record->update(['status' => ContentLexemeCandidate::STATUS_EDITED]);
    }
}
