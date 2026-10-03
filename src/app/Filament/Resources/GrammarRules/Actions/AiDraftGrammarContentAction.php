<?php

namespace App\Filament\Resources\GrammarRules\Actions;

use App\Modules\Ai\Application\AiFieldEditService;
use App\Modules\Content\Application\Ai\GrammarRuleAiContentBuilder;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Support\AiConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Fills the live edit form with an AI-proposed summary/body — never saves
 * directly. The admin reviews the filled-in fields (and can still edit them)
 * and only the page's own Save button persists anything, which is also what
 * triggers GrammarRule's HasRevisions history entry.
 */
class AiDraftGrammarContentAction
{
    public static function make(GrammarRule $record): Action
    {
        return Action::make('aiDraftGrammarContent')
            ->label('AI: Draft/Improve')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->visible(fn (): bool => AiConfig::isEnabled())
            ->schema([
                Textarea::make('instruction')
                    ->label('Instruction (optional)')
                    ->helperText('Leave empty to (re)generate from scratch, or ask for a specific change, e.g. "make it shorter" or "add more common mistakes".')
                    ->rows(2),
            ])
            ->action(function (array $data, EditRecord $livewire) use ($record): void {
                $proposal = app(AiFieldEditService::class)->propose(
                    $record,
                    app(GrammarRuleAiContentBuilder::class),
                    filled($data['instruction'] ?? null) ? $data['instruction'] : null
                );

                $updates = array_intersect_key($proposal, array_flip(['summary', 'body']));

                if ($updates === []) {
                    Notification::make()->title('AI did not return usable content')->warning()->send();

                    return;
                }

                $livewire->form->fill([...$livewire->form->getState(), ...$updates]);

                Notification::make()
                    ->title('Draft filled in — review and Save to keep it')
                    ->success()
                    ->send();
            });
    }
}
