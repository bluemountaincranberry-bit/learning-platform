<?php

namespace App\Filament\Resources\GrammarRules\RelationManagers;

use App\Modules\Content\Domain\Models\GrammarRuleExercise;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExercisesRelationManager extends RelationManager
{
    protected static string $relationship = 'exercises';

    protected static ?string $title = 'Exercises';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(array_combine(GrammarRuleExercise::TYPES, GrammarRuleExercise::TYPES))
                ->required()
                ->live(),
            Select::make('status')
                ->options(array_combine(GrammarRuleExercise::STATUSES, GrammarRuleExercise::STATUSES))
                ->default(GrammarRuleExercise::STATUS_DRAFT)
                ->required(),
            TextInput::make('instruction')
                ->helperText('Short task line, e.g. "Make it a question". Needed for transform.')
                ->required(fn (Get $get): bool => $get('type') === GrammarRuleExercise::TYPE_TRANSFORM),
            Textarea::make('prompt')->required()->rows(2)->columnSpanFull(),
            TextInput::make('answer')
                ->label('Correct answer')
                ->helperText('Fill the gap: the text for the blank. Build / transform / fix: the full sentence.')
                ->visible(fn (Get $get): bool => in_array($get('type'), GrammarRuleExercise::TEXT_ANSWER_TYPES, true))
                ->required(fn (Get $get): bool => in_array($get('type'), GrammarRuleExercise::TEXT_ANSWER_TYPES, true)),
            Textarea::make('accepted_answers')
                ->label('Other accepted answers')
                ->helperText('One per line. Case, final punctuation and contractions are already ignored.')
                ->rows(2)
                ->visible(fn (Get $get): bool => in_array($get('type'), GrammarRuleExercise::TEXT_ANSWER_TYPES, true))
                ->dehydrateStateUsing(fn (?string $state): ?array => self::lines($state) ?: null)
                ->formatStateUsing(fn ($state): string => is_array($state) ? implode("\n", $state) : (string) $state),
            Textarea::make('tiles')
                ->helperText('Build the sentence: the answer split into words or short chunks, one per line, in the correct order.')
                ->rows(3)
                ->visible(fn (Get $get): bool => $get('type') === GrammarRuleExercise::TYPE_BUILD)
                ->required(fn (Get $get): bool => $get('type') === GrammarRuleExercise::TYPE_BUILD)
                ->dehydrateStateUsing(fn (?string $state): ?array => self::lines($state) ?: null)
                ->formatStateUsing(fn ($state): string => is_array($state) ? implode("\n", $state) : (string) $state),
            Textarea::make('options')
                ->helperText('One option per line.')
                ->rows(3)
                ->visible(fn (Get $get): bool => $get('type') === GrammarRuleExercise::TYPE_MULTIPLE_CHOICE)
                ->dehydrateStateUsing(fn (?string $state): array => self::lines($state))
                ->formatStateUsing(fn ($state): string => is_array($state) ? implode("\n", $state) : (string) $state),
            TextInput::make('answer_index')
                ->label('Correct option (0-based index)')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => $get('type') === GrammarRuleExercise::TYPE_MULTIPLE_CHOICE)
                ->required(fn (Get $get): bool => $get('type') === GrammarRuleExercise::TYPE_MULTIPLE_CHOICE),
            Textarea::make('hint')
                ->helperText('Shown after the first wrong answer. Must not contain the answer.')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('explanation')->rows(2)->columnSpanFull(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    /** @return list<string> */
    private static function lines(?string $state): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $state))));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('prompt')->wrap()->limit(80),
                TextColumn::make('origin')->badge()->color(fn (string $state): string => $state === GrammarRuleExercise::ORIGIN_AI ? 'info' : 'gray'),
                TextColumn::make('reports_count')->counts('reports')->label('Reports')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GrammarRuleExercise::STATUS_PUBLISHED => 'success',
                        GrammarRuleExercise::STATUS_ARCHIVED => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                Action::make('publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (GrammarRuleExercise $record): bool => $record->status !== GrammarRuleExercise::STATUS_PUBLISHED)
                    ->action(fn (GrammarRuleExercise $record) => $record->update(['status' => GrammarRuleExercise::STATUS_PUBLISHED])),
                Action::make('unpublish')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (GrammarRuleExercise $record): bool => $record->status === GrammarRuleExercise::STATUS_PUBLISHED)
                    ->action(fn (GrammarRuleExercise $record) => $record->update(['status' => GrammarRuleExercise::STATUS_DRAFT])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
