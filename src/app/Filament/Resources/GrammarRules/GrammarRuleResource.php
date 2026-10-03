<?php

namespace App\Filament\Resources\GrammarRules;

use App\Filament\Resources\GrammarRules\Pages\CreateGrammarRule;
use App\Filament\Resources\GrammarRules\Pages\EditGrammarRule;
use App\Filament\Resources\GrammarRules\Pages\ListGrammarRules;
use App\Filament\Resources\GrammarRules\RelationManagers\ExamplesRelationManager;
use App\Filament\Resources\GrammarRules\RelationManagers\ExercisesRelationManager;
use App\Filament\Resources\GrammarRules\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\GrammarRules\Schemas\GrammarRuleForm;
use App\Filament\Resources\GrammarRules\Tables\GrammarRulesTable;
use App\Modules\Content\Domain\Models\GrammarRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class GrammarRuleResource extends Resource
{
    protected static ?string $model = GrammarRule::class;

    protected static ?string $navigationLabel = 'Grammar Rules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Grammar';

    public static function form(Schema $schema): Schema
    {
        return GrammarRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GrammarRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ExamplesRelationManager::class,
            ExercisesRelationManager::class,
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrammarRules::route('/'),
            'create' => CreateGrammarRule::route('/create'),
            'edit' => EditGrammarRule::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-content');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage-content');
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('manage-content');
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('manage-content');
    }
}
