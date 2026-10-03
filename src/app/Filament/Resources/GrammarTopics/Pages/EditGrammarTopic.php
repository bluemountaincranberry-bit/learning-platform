<?php

namespace App\Filament\Resources\GrammarTopics\Pages;

use App\Filament\Resources\GrammarTopics\GrammarTopicResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrammarTopic extends EditRecord
{
    protected static string $resource = GrammarTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
