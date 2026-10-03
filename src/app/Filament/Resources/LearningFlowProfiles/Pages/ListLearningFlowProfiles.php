<?php

namespace App\Filament\Resources\LearningFlowProfiles\Pages;

use App\Filament\Resources\LearningFlowProfiles\LearningFlowProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLearningFlowProfiles extends ListRecords
{
    protected static string $resource = LearningFlowProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
