<x-filament-panels::page>
    <div class="mb-4">
        <x-filament::link :href="\App\Filament\Pages\AgentTraces::getUrl()">
            &larr; Back to traces
        </x-filament::link>
    </div>

    @if ($trace)
        <div class="mb-4 flex flex-wrap gap-4 text-sm text-gray-500">
            <span>Agent: <span class="text-gray-900 dark:text-gray-100">{{ $trace->entry_agent_type ?? '—' }}</span></span>
            <span>Started: <span class="text-gray-900 dark:text-gray-100">{{ $trace->started_at?->format('Y-m-d H:i:s') ?? '—' }}</span></span>
            <span>Total cost: <span class="text-gray-900 dark:text-gray-100">{{ $trace->total_cost_usd !== null ? '$' . number_format($trace->total_cost_usd, 4) : '—' }}</span></span>
        </div>
    @else
        <div class="mb-4 text-sm text-gray-500">No `agent_traces` summary row found for this trace_id — showing raw spans only.</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Span</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Tokens</th>
                    <th class="px-4 py-3">Cost</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($spans as $span)
                    <tr>
                        <td class="px-4 py-3">
                            <span style="padding-left: {{ $this->depthFor($span) * 1.25 }}rem" class="inline-block">
                                @if ($this->depthFor($span) > 0)
                                    <span class="text-gray-400">&#8627;</span>
                                @endif
                                {{ $span->name }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <x-filament::badge color="gray">{{ $span->span_type }}</x-filament::badge>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $span->started_at?->format('H:i:s.v') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $span->duration_ms !== null ? number_format($span->duration_ms) . ' ms' : '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($span->prompt_tokens !== null || $span->completion_tokens !== null)
                                {{ $span->prompt_tokens ?? 0 }} in / {{ $span->completion_tokens ?? 0 }} out
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $span->cost_usd !== null ? '$' . number_format($span->cost_usd, 6) : '—' }}</td>
                        <td class="px-4 py-3">
                            <x-filament::badge :color="$span->status === 'error' ? 'danger' : ($span->status === 'ok' ? 'success' : 'gray')">
                                {{ $span->status ?? 'open' }}
                            </x-filament::badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No spans found for this trace.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
