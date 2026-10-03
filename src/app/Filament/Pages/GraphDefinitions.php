<?php

namespace App\Filament\Pages;

use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\User\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only list over graph_definitions — GraphDefinitions is to
 * PersistedGraphDefinition what AgentGraphRuns already is to
 * AgentGraphRun (task 6.8's pattern), except this lists *definitions*
 * (the wiring an admin can edit) rather than *runs* (executions of one).
 * Editing happens on the canvas (Vue Flow), not here — see
 * PromptTemplates' docblock for the same split.
 */
class GraphDefinitions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationLabel = 'Graph Definitions';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Builder';

    protected string $view = 'filament.pages.graph-definitions';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && $user->can('manage-ai-builder');
    }

    /**
     * @return Collection<int, PersistedGraphDefinition>
     */
    public function definitions(): Collection
    {
        return PersistedGraphDefinition::query()->with('activeVersion')->withCount('versions')->orderBy('key')->get();
    }

    /**
     * Graph names that exist in `config('ai.graph.definitions')` (hand-built
     * PHP classes) but have no `graph_definitions` row yet — without this,
     * an admin who wants to *start* overriding e.g. `ai_analysis` has no
     * link into the canvas at all, since definitions() above only ever
     * shows keys that already have a draft.
     *
     * @return array<int, string>
     */
    public function graphNamesWithoutOverride(): array
    {
        $overridden = $this->definitions()->pluck('key')->all();

        return array_values(array_diff(array_keys(config('ai.graph.definitions', [])), $overridden));
    }
}
