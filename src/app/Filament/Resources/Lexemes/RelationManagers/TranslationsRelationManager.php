<?php

namespace App\Filament\Resources\Lexemes\RelationManagers;

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

class TranslationsRelationManager extends RelationManager
{
    protected static string $relationship = 'translations';

    protected static ?string $title = 'Translations';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('language')
                ->required()
                ->maxLength(8)
                ->helperText('The target/native language this gloss is written in, e.g. "ru".'),
            Textarea::make('translation')->required()->rows(2)->columnSpanFull(),
            Toggle::make('is_primary')
                ->helperText('The primary translation is what users see next to the word for this language. Only one per (content, language) should be primary.'),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation')->wrap()->limit(80),
                TextColumn::make('language')->toggleable(),
                IconColumn::make('is_primary')->boolean(),
                TextColumn::make('content.title')->label('Source content')->placeholder('Curated (global)')->toggleable(),
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
