<?php

namespace App\Filament\Resources\Contents\Pages;

use App\Filament\Resources\Contents\Actions\AcceptTranscriptAction;
use App\Filament\Resources\Contents\Actions\ContentPipelineAction;
use App\Filament\Resources\Contents\ContentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditContent extends EditRecord
{
    protected static string $resource = ContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContentPipelineAction::make(pageRecord: $this->record),
            AcceptTranscriptAction::make(pageRecord: $this->record),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $oldTranscript = trim((string) ($this->record->source_text ?? ''));
        $newTranscript = trim((string) ($data['source_text'] ?? ''));

        if ($oldTranscript !== $newTranscript) {
            $data = [
                ...$data,
                ...$this->record->transcriptChangedAttributes($newTranscript),
            ];
        }

        return $data;
    }
}
