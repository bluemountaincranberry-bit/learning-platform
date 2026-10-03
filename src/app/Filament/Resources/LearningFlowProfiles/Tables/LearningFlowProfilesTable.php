<?php

namespace App\Filament\Resources\LearningFlowProfiles\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LearningFlowProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('slug')->searchable(),
            TextColumn::make('version')->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('assignments_count')->counts('assignments')->label('Assignments'),
            TextColumn::make('published_at')->dateTime()->toggleable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
