<?php

namespace App\Filament\Resources\Contents\Pages;

use App\Filament\Resources\Contents\ContentResource;
use App\Modules\Ai\Interfaces\Jobs\ComputeEmbeddingsJob;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Ai\Domain\Models\LexemeEmbedding;
use App\Support\AiConfig;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListContents extends ListRecords
{
    protected static string $resource = ContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reindexEmbeddings')
                ->label('Re-index embeddings')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => AiConfig::isEnabled())
                ->requiresConfirmation()
                ->modalHeading('Re-index lexeme embeddings')
                ->modalDescription('Dispatch jobs to compute and store embeddings for lexemes that do not have them yet. This may take a while.')
                ->action(function (): void {
                    $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');
                    $existingIds = LexemeEmbedding::query()
                        ->where('model_version', $modelVersion)
                        ->pluck('content_lexeme_id')
                        ->all();
                    $ids = ContentLexeme::query()
                        ->whereNotIn('id', $existingIds)
                        ->pluck('id')
                        ->all();
                    if (empty($ids)) {
                        Notification::make()
                            ->title('No lexemes to process')
                            ->body('All lexemes already have embeddings for the current model.')
                            ->success()
                            ->send();

                        return;
                    }
                    $chunkSize = 50;
                    $chunks = array_chunk($ids, $chunkSize);
                    foreach ($chunks as $chunk) {
                        ComputeEmbeddingsJob::dispatch($chunk);
                    }
                    Notification::make()
                        ->title('Re-index started')
                        ->body(sprintf('Dispatched %d job(s) for %d lexeme(s).', count($chunks), count($ids)))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'moderation' => Tab::make()
                ->label('Moderation queue')
                ->modifyQueryUsing(fn ($query) => $query
                    ->where('origin', 'user-submitted')
                    ->where('status', 'pending')),
            'pipeline' => Tab::make()
                ->label('Pipeline')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['pending', 'processing', 'failed'])),
            'ready' => Tab::make()
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'ready')),
            'failed' => Tab::make()
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['failed', 'rejected'])),
        ];
    }
}
