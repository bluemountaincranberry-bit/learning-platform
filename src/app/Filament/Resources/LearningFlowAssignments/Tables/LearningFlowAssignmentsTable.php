<?php

namespace App\Filament\Resources\LearningFlowAssignments\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LearningFlowAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('profile.name')->label('Profile')->sortable(),
            TextColumn::make('user.name')->label('User')->placeholder('Scope'),
            TextColumn::make('language')->placeholder('Any'),
            TextColumn::make('level')->placeholder('Any'),
            TextColumn::make('learning_goal')->placeholder('Any'),
            TextColumn::make('priority')->sortable(),
            TextColumn::make('starts_at')->dateTime()->toggleable(),
            TextColumn::make('ends_at')->dateTime()->toggleable(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
