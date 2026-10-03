<?php

namespace App\Filament\Resources\GrammarTopics\Tables;

use App\Modules\Content\Domain\Models\GrammarTopic;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GrammarTopicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->toggleable(),
                TextColumn::make('language')->toggleable(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GrammarTopic::STATUS_ACTIVE => 'success',
                        GrammarTopic::STATUS_ARCHIVED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('rules_count')->counts('rules')->label('Rules'),
                TextColumn::make('sort_order')->sortable()->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(GrammarTopic::STATUSES, GrammarTopic::STATUSES)),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
