<?php

namespace App\Filament\Resources\Contents\Tables;

use App\Filament\Resources\Contents\Actions\AcceptTranscriptAction;
use App\Filament\Resources\Contents\Actions\ContentPipelineAction;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContentsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('origin')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'success',
                        'processing' => 'info',
                        'failed', 'rejected' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('language')->toggleable(),
                TextColumn::make('level')->label('CEFR')->badge()->toggleable(),
                TextColumn::make('processing_requested_at')
                    ->label('Pipeline requested')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('processing_completed_at')
                    ->label('Pipeline completed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('processing_failure_reason')
                    ->label('Pipeline error')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transcript_accepted_at')
                    ->label('Transcript accepted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('analysis_stale_at')
                    ->label('Analysis stale')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('author.name')->label('Author')->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(array_combine(Content::TYPES, Content::TYPES)),
                SelectFilter::make('status')
                    ->options(array_combine(Content::STATUSES, Content::STATUSES)),
                SelectFilter::make('origin')
                    ->options([
                        'curated' => 'curated',
                        'user-submitted' => 'user-submitted',
                        'ai-chat' => 'ai-chat',
                    ]),
                SelectFilter::make('level')
                    ->label('CEFR level')
                    ->options(array_combine(Content::CEFR_LEVELS, Content::CEFR_LEVELS)),
            ])
            ->recordActions([
                ContentPipelineAction::make(),
                AcceptTranscriptAction::make(),
                Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Content $record): bool => $record->origin === 'user-submitted' && $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Content $record): void {
                        $record->update([
                            'moderator_id' => auth()->id(),
                            'moderated_at' => now(),
                            'moderation_comment' => null,
                        ]);

                        $result = app(ContentProcessingOrchestratorInterface::class)->request($record);

                        if ($result->accepted) {
                            Notification::make()
                                ->title('Processing started via pipeline')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($result->reason ?? 'Unable to start processing')
                            ->warning()
                            ->send();
                    }),
                Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (Content $record): bool => $record->origin === 'user-submitted' && $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('moderation_comment')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Content $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'moderation_comment' => $data['moderation_comment'],
                            'moderator_id' => auth()->id(),
                            'moderated_at' => now(),
                        ]);
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        if (config('scout.driver') === 'elastic') {
            $table->searchUsing(function (Builder $query, string $search): void {
                $query->whereIn('id', Content::search($search)->keys());
            });
        }

        return $table;
    }
}
