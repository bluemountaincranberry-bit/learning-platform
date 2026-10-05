<?php

namespace App\Filament\Resources\Lexemes;

use App\Filament\Resources\Lexemes\Pages\CreateLexeme;
use App\Filament\Resources\Lexemes\Pages\EditLexeme;
use App\Filament\Resources\Lexemes\Pages\ListLexemes;
use App\Filament\Resources\Lexemes\RelationManagers\AssociationsRelationManager;
use App\Filament\Resources\Lexemes\RelationManagers\ExamplesRelationManager;
use App\Filament\Resources\Lexemes\RelationManagers\TranslationsRelationManager;
use App\Filament\Resources\Lexemes\Schemas\LexemeForm;
use App\Filament\Resources\Lexemes\Tables\LexemesTable;
use App\Modules\Content\Domain\Models\Lexeme;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

class LexemeResource extends Resource
{
    protected static ?string $model = Lexeme::class;

    protected static ?string $navigationLabel = 'Lexemes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'lemma';

    public static function form(Schema $schema): Schema
    {
        return LexemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LexemesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('owner_user_id');
    }

    public static function getRelations(): array
    {
        return [
            ExamplesRelationManager::class,
            TranslationsRelationManager::class,
            AssociationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLexemes::route('/'),
            'create' => CreateLexeme::route('/create'),
            'edit' => EditLexeme::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-content') || Gate::allows('moderate-content');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage-content');
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('manage-content') || Gate::allows('moderate-content');
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('manage-content');
    }
}
