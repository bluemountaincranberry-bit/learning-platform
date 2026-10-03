<?php

namespace App\Filament\Resources\Lexemes\Tables;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LexemesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lemma')->searchable()->sortable(),
                TextColumn::make('language')->toggleable(),
                TextColumn::make('part_of_speech')->label('Part of speech')->badge()->toggleable(),
                TextColumn::make('level')->label('CEFR')->badge()->toggleable(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Lexeme::STATUS_PUBLISHED => 'success',
                        Lexeme::STATUS_ARCHIVED => 'gray',
                        Lexeme::STATUS_REVIEW => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('examples_count')->counts('examples')->label('Examples'),
                TextColumn::make('translations_count')->counts('translations')->label('Translations'),
                TextColumn::make('associations_count')->counts('associations')->label('Related'),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(Lexeme::STATUSES, Lexeme::STATUSES)),
                SelectFilter::make('level')->label('CEFR level')->options(array_combine(Content::CEFR_LEVELS, Content::CEFR_LEVELS)),
                SelectFilter::make('part_of_speech')->label('Part of speech')->options(Lexeme::PARTS_OF_SPEECH),
                SelectFilter::make('language')
                    ->options(fn () => Lexeme::query()->distinct()->orderBy('language')->pluck('language', 'language')->all()),
            ])
            ->defaultSort('lemma')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
