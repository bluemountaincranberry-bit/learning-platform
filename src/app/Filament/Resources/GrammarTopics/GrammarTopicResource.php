<?php

namespace App\Filament\Resources\GrammarTopics;

use App\Filament\Resources\GrammarTopics\Pages\CreateGrammarTopic;
use App\Filament\Resources\GrammarTopics\Pages\EditGrammarTopic;
use App\Filament\Resources\GrammarTopics\Pages\ListGrammarTopics;
use App\Filament\Resources\GrammarTopics\Schemas\GrammarTopicForm;
use App\Filament\Resources\GrammarTopics\Tables\GrammarTopicsTable;
use App\Modules\Content\Domain\Models\GrammarTopic;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class GrammarTopicResource extends Resource
{
    protected static ?string $model = GrammarTopic::class;

    protected static ?string $navigationLabel = 'Grammar Topics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Grammar';

    public static function form(Schema $schema): Schema
    {
        return GrammarTopicForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GrammarTopicsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrammarTopics::route('/'),
            'create' => CreateGrammarTopic::route('/create'),
            'edit' => EditGrammarTopic::route('/{record}/edit'),
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
