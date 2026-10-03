<?php

namespace App\Filament\Resources\LearningFlowProfiles\Pages;

use App\Filament\Resources\LearningFlowProfiles\LearningFlowProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLearningFlowProfile extends CreateRecord
{
    protected static string $resource = LearningFlowProfileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
