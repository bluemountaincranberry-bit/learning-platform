<?php

namespace App\Filament\Resources\LearningFlowProfiles;

use App\Filament\Resources\LearningFlowProfiles\Pages\CreateLearningFlowProfile;
use App\Filament\Resources\LearningFlowProfiles\Pages\EditLearningFlowProfile;
use App\Filament\Resources\LearningFlowProfiles\Pages\ListLearningFlowProfiles;
use App\Filament\Resources\LearningFlowProfiles\Schemas\LearningFlowProfileForm;
use App\Filament\Resources\LearningFlowProfiles\Tables\LearningFlowProfilesTable;
use App\Modules\Learning\Domain\Models\LearningFlowProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LearningFlowProfileResource extends Resource
{
    protected static ?string $model = LearningFlowProfile::class;

    protected static ?string $navigationLabel = 'Learning flows';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Learning';

    public static function form(Schema $schema): Schema
    {
        return LearningFlowProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LearningFlowProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListLearningFlowProfiles::route('/'), 'create' => CreateLearningFlowProfile::route('/create'), 'edit' => EditLearningFlowProfile::route('/{record}/edit')];
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
        return Gate::allows('manage-learning-flows') && $record->status !== 'published';
    }
}
