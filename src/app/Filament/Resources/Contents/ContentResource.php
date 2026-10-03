<?php

namespace App\Filament\Resources\Contents;

use App\Filament\Resources\Contents\Pages\CreateContent;
use App\Filament\Resources\Contents\Pages\EditContent;
use App\Filament\Resources\Contents\Pages\ListContents;
use App\Filament\Resources\Contents\Pages\ViewContent;
use App\Filament\Resources\Contents\RelationManagers\GrammarCandidatesRelationManager;
use App\Filament\Resources\Contents\RelationManagers\GrammarRulesRelationManager;
use App\Filament\Resources\Contents\RelationManagers\LexemeCandidatesRelationManager;
use App\Filament\Resources\Contents\RelationManagers\LexemesRelationManager;
use App\Filament\Resources\Contents\Schemas\ContentForm;
use App\Filament\Resources\Contents\Schemas\ContentInfolist;
use App\Filament\Resources\Contents\Tables\ContentsTable;
use App\Modules\Content\Domain\Models\Content;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ContentResource extends Resource
{
    protected static ?string $model = Content::class;

    protected static ?string $navigationLabel = 'Contents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ContentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LexemesRelationManager::class,
            LexemeCandidatesRelationManager::class,
            GrammarCandidatesRelationManager::class,
            GrammarRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContents::route('/'),
            'create' => CreateContent::route('/create'),
            'view' => ViewContent::route('/{record}'),
            'edit' => EditContent::route('/{record}/edit'),
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
