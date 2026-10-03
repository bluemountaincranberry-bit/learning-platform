<?php

namespace App\Filament\Resources\LearningFlowAssignments;

use App\Filament\Resources\LearningFlowAssignments\Pages\CreateLearningFlowAssignment;
use App\Filament\Resources\LearningFlowAssignments\Pages\EditLearningFlowAssignment;
use App\Filament\Resources\LearningFlowAssignments\Pages\ListLearningFlowAssignments;
use App\Filament\Resources\LearningFlowAssignments\Schemas\LearningFlowAssignmentForm;
use App\Filament\Resources\LearningFlowAssignments\Tables\LearningFlowAssignmentsTable;
use App\Modules\Learning\Domain\Models\LearningFlowAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LearningFlowAssignmentResource extends Resource
{
    protected static ?string $model = LearningFlowAssignment::class;

    protected static ?string $navigationLabel = 'Flow assignments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Learning';

    public static function form(Schema $schema): Schema
    {
        return LearningFlowAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LearningFlowAssignmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListLearningFlowAssignments::route('/'), 'create' => CreateLearningFlowAssignment::route('/create'), 'edit' => EditLearningFlowAssignment::route('/{record}/edit')];
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-learning-flows');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage-learning-flows');
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('manage-learning-flows');
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('manage-learning-flows');
    }
}
