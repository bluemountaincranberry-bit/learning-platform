<?php

namespace App\Filament\Resources\LearningFlowAssignments\Pages;

use App\Filament\Resources\LearningFlowAssignments\LearningFlowAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLearningFlowAssignment extends EditRecord
{
    protected static string $resource = LearningFlowAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
