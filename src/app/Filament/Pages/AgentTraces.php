<?php

namespace App\Filament\Pages;

use App\Modules\Ai\Domain\Models\AgentTrace;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Task 6.8: minimal read-only admin visibility into what the AI platform's
 * been doing — the roadmap is explicit this should not be a dashboard with
 * charts, just tables over data that already exists (`agent_traces`,
 * task 5.2's denormalized per-trace summary). No Filament Resource (no
 * create/edit/delete makes sense for a trace), so this is a plain custom
 * Page — same shape as the existing `ContentAgentChat` page, not the
 * Livewire `InteractsWithTable` table-builder, to keep this one page as
 * simple as the data it shows. Pagination is plain Laravel pagination
 * (full page reload on page click), which is fine for an occasional admin
 * lookup and avoids wiring a Livewire table component for a read-only view.
 */
class AgentTraces extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Agent Traces';

    protected static string|\UnitEnum|null $navigationGroup = 'Observability';

    protected string $view = 'filament.pages.agent-traces';

    /**
     * A plain method, not a mounted public property: Livewire has no
     * built-in synthesizer for a raw `LengthAwarePaginator` (it can only
     * sync plain values, Eloquent models/collections, and a few other known
     * shapes between requests) — computing it fresh on every render avoids
     * that entirely, and is cheap enough for an admin-only, low-traffic
     * page. Pagination itself still works via plain `?page=` links causing
     * an ordinary full page load, not a Livewire-reactive one.
     */
    public function traces(): LengthAwarePaginator
    {
        return AgentTrace::query()
            ->latest('started_at')
            ->paginate(25);
    }
}
