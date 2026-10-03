<?php

namespace App\Filament\Resources\GrammarRules\Tables;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GrammarRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('topic.name')->label('Topic')->sortable()->toggleable(),
                TextColumn::make('language')->toggleable(),
                TextColumn::make('level')->label('CEFR')->badge()->toggleable(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GrammarRule::STATUS_PUBLISHED => 'success',
                        GrammarRule::STATUS_ARCHIVED => 'gray',
                        GrammarRule::STATUS_REVIEW => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('examples_count')->counts('examples')->label('Examples'),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(GrammarRule::STATUSES, GrammarRule::STATUSES)),
                SelectFilter::make('level')->label('CEFR level')->options(array_combine(Content::CEFR_LEVELS, Content::CEFR_LEVELS)),
                SelectFilter::make('topic_id')
                    ->label('Topic')
                    ->relationship('topic', 'name'),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
