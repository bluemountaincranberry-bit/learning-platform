<?php

namespace App\Filament\Resources\LearningFlowAssignments\Pages;

use App\Filament\Resources\LearningFlowAssignments\LearningFlowAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLearningFlowAssignments extends ListRecords
{
    protected static string $resource = LearningFlowAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
