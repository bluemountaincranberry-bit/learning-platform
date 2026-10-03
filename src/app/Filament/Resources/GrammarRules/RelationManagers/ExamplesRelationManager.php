<?php

namespace App\Filament\Resources\GrammarRules\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamplesRelationManager extends RelationManager
{
    protected static string $relationship = 'examples';

    protected static ?string $title = 'Examples';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('example')->required()->rows(2)->columnSpanFull(),
            Textarea::make('translation')->rows(2)->columnSpanFull(),
            TextInput::make('language')->required()->maxLength(8)->default('en'),
            TextInput::make('translation_language')->maxLength(8),
            Toggle::make('is_primary'),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('example')->wrap()->limit(80),
                TextColumn::make('translation')->wrap()->limit(80)->placeholder('—'),
                TextColumn::make('language')->toggleable(),
                IconColumn::make('is_primary')->boolean(),
                TextColumn::make('content.title')->label('Source content')->placeholder('Curated')->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
