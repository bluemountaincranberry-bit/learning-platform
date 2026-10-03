<?php

namespace App\Filament\Resources\LearningFlowAssignments\Schemas;

use App\Modules\Learning\Domain\Models\LearningFlowProfile;
use App\Modules\User\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LearningFlowAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('learning_flow_profile_id')->label('Flow profile')->options(fn () => LearningFlowProfile::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required()->native(false),
            Select::make('user_id')->label('Specific user (optional)')->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))->searchable()->nullable()->native(false),
            TextInput::make('language')->maxLength(8)->placeholder('en'),
            TextInput::make('level')->maxLength(2)->placeholder('B1'),
            TextInput::make('learning_goal')->maxLength(32)->placeholder('conversation'),
            TextInput::make('priority')->numeric()->default(0)->minValue(0)->required(),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
        ]);
    }
}
