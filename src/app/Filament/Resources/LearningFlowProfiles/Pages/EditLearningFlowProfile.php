<?php

namespace App\Filament\Resources\LearningFlowProfiles\Pages;

use App\Filament\Resources\LearningFlowProfiles\LearningFlowProfileResource;
use App\Modules\User\Models\User;
use App\Modules\Learning\Application\LearningFlowResolver;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditLearningFlowProfile extends EditRecord
{
    protected static string $resource = LearningFlowProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview for learner')
                ->form([
                    Select::make('user_id')->label('Learner')->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required()->native(false),
                ])
                ->action(function (array $data): void {
                    $user = User::query()->findOrFail((int) $data['user_id']);
                    $resolved = app(LearningFlowResolver::class)->resolve($user);
                    Notification::make()->title("Effective flow: {$resolved['profile']->name} v{$resolved['profile']->version}")->body(json_encode($resolved['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->info()->persistent()->send();
                }),
            Action::make('clone')->label('Clone as draft')->action(function (): void {
                $clone = $this->record->replicate(['published_at', 'created_by']);
                $clone->name = $this->record->name.' (draft)';
                $clone->slug = Str::slug($this->record->slug.'-draft-'.now()->format('His'));
                $clone->status = 'draft';
                $clone->version = ((int) $this->record->version) + 1;
                $clone->created_by = auth()->id();
                $clone->save();
                $this->redirect(LearningFlowProfileResource::getUrl('edit', ['record' => $clone]));
            }),
            Action::make('publish')->label('Publish')->color('success')->requiresConfirmation()->visible(fn (): bool => $this->record->status !== 'published')->action(function (): void {
                $this->record->update(['status' => 'published', 'published_at' => now(), 'version' => ((int) $this->record->version) + 1]);
                Notification::make()->title('Learning flow published')->success()->send();
            }),
            DeleteAction::make(),
        ];
    }
}
