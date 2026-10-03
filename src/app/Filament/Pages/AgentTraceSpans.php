<?php

namespace App\Filament\Pages;

use App\Modules\Ai\Domain\Models\AgentTrace;
use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * Task 6.8: drill-down from AgentTraces — "переход в дерево
 * agent_trace_spans одного трейса". Reached via a plain `?trace_id=`
 * query-string link (AgentTraces' "View spans" action), read back in
 * mount() the same way a query-param-driven page always would — no custom
 * route/parameter binding needed for a one-off admin lookup. Not in the
 * main nav (`shouldRegisterNavigation` false): it only makes sense arrived
 * at from a specific trace row, never as a standalone destination.
 */
class AgentTraceSpans extends Page
{
    protected static ?string $navigationLabel = 'Agent Trace Spans';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.agent-trace-spans';

    public string $traceId = '';

    public ?AgentTrace $trace = null;

    /**
     * @var Collection<int, AgentTraceSpan>
     */
    public Collection $spans;

    /**
     * `$trace_id` is only ever populated directly by `Livewire::test()`'s
     * $params argument (component-mount testing bypasses real HTTP
     * routing); a real page load falls through to reading the actual
     * request's `?trace_id=` query string, which is how AgentTraces'
     * "View spans" link (`AgentTraceSpans::getUrl(['trace_id' => ...])`,
     * a plain query string since it isn't a route segment) is read back.
     */
    public function mount(?string $trace_id = null): void
    {
        $this->traceId = $trace_id ?? (string) request()->query('trace_id', '');
        $this->trace = AgentTrace::query()->where('trace_id', $this->traceId)->first();
        $this->spans = AgentTraceSpan::query()
            ->where('trace_id', $this->traceId)
            ->orderBy('started_at')
            ->get();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->traceId !== '' ? "Trace {$this->traceId}" : 'Trace';
    }

    /**
     * How deeply nested `$span` is under `parent_span_id` chains within this
     * trace's own span set — purely cosmetic indentation so the flat list
     * still reads as a tree without a real tree-rendering component. Capped
     * at the span count as a defensive bound against a cyclical
     * parent_span_id, which should never happen but must never hang a page.
     */
    public function depthFor(AgentTraceSpan $span): int
    {
        $byId = $this->spans->keyBy('span_id');
        $depth = 0;
        $current = $span;
        $maxDepth = $this->spans->count();

        while ($current->parent_span_id !== null && $depth < $maxDepth) {
            $parent = $byId->get($current->parent_span_id);
            if ($parent === null) {
                break;
            }
            $current = $parent;
            $depth++;
        }

        return $depth;
    }
}
