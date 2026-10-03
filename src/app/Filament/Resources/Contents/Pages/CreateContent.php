<?php

namespace App\Filament\Resources\Contents\Pages;

use App\Filament\Resources\Contents\ContentResource;
use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateContent extends CreateRecord
{
    protected static string $resource = ContentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        $content = $this->record;

        $hasSourceText = trim((string) ($content->source_text ?? '')) !== '';
        $isFetchableYoutube = $content->type === 'youtube' && filled($content->source_url);

        if (! $hasSourceText && ! $isFetchableYoutube) {
            return;
        }

        $result = app(ContentProcessingOrchestratorInterface::class)->request($content);

        if ($result->accepted) {
            Notification::make()
                ->title('Pipeline started')
                ->body($isFetchableYoutube && ! $hasSourceText
                    ? 'Fetching the YouTube transcript now — the content will move to "ready" once it\'s tokenized.'
                    : 'Tokenizing the text now — the content will move to "ready" shortly.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title($result->reason ?? 'Could not start the pipeline automatically')
            ->body('Use "Start pipeline" on this content once it\'s ready.')
            ->warning()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return ContentResource::getUrl('view', ['record' => $this->record]);
    }
}
