<?php

namespace App\Filament\Resources\Contents\Pages;

use App\Filament\Resources\Contents\Actions\AcceptTranscriptAction;
use App\Filament\Resources\Contents\Actions\AnalyzeWithAiAction;
use App\Filament\Resources\Contents\Actions\AnalyzeWithAiGraphAction;
use App\Filament\Resources\Contents\Actions\ApplyAiCandidatesAction;
use App\Filament\Resources\Contents\Actions\ContentPipelineAction;
use App\Filament\Resources\Contents\Actions\ResetAiAnalysisAction;
use App\Filament\Resources\Contents\ContentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContent extends ViewRecord
{
    protected static string $resource = ContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContentPipelineAction::make(pageRecord: $this->record),
            AcceptTranscriptAction::make(pageRecord: $this->record),
            AnalyzeWithAiAction::make(pageRecord: $this->record),
            AnalyzeWithAiGraphAction::make(pageRecord: $this->record),
            ResetAiAnalysisAction::make(pageRecord: $this->record),
            ApplyAiCandidatesAction::make(pageRecord: $this->record),
            Action::make('pasteTranscript')
                ->label('Paste transcript manually')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === 'failed' && $this->record->type === 'youtube')
                ->url(fn (): string => ContentResource::getUrl('edit', ['record' => $this->record]))
                ->openUrlInNewTab(false),
            EditAction::make(),
        ];
    }
}
