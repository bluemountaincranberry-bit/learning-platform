<?php

namespace App\Filament\Pages;

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\User\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only list over prompt_templates — same "plain Blade table over
 * existing data, not a second editor" shape as AgentGraphRuns/AgentTraces
 * (task 6.8). Actual editing (drafting a new version, publishing) happens
 * through the graph-builder canvas (Vue Flow, admin-gated SPA route) via
 * the PromptTemplateController API — this page's job is visibility: which
 * prompts have an active override at all, and a link into the canvas to
 * change one.
 */
class PromptTemplates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Prompt Templates';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Builder';

    protected string $view = 'filament.pages.prompt-templates';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && $user->can('manage-ai-builder');
    }

    /**
     * @return Collection<int, PromptTemplate>
     */
    public function templates(): Collection
    {
        return PromptTemplate::query()->with('activeVersion')->withCount('versions')->orderBy('key')->get();
    }
}
