<x-filament-panels::page>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Trace</th>
                    <th class="px-4 py-3">Agent</th>
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Cost</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($this->traces() as $trace)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ Str::limit($trace->trace_id, 12, '…') }}</td>
                        <td class="px-4 py-3">{{ $trace->entry_agent_type ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $trace->started_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $trace->total_duration_ms !== null ? number_format($trace->total_duration_ms) . ' ms' : '—' }}</td>
                        <td class="px-4 py-3">{{ $trace->total_cost_usd !== null ? '$' . number_format($trace->total_cost_usd, 4) : '—' }}</td>
                        <td class="px-4 py-3">
                            <x-filament::badge :color="$trace->status === 'error' ? 'danger' : 'success'">
                                {{ $trace->status ?? 'unknown' }}
                            </x-filament::badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-filament::link :href="\App\Filament\Pages\AgentTraceSpans::getUrl(['trace_id' => $trace->trace_id])">
                                View spans
                            </x-filament::link>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No agent traces recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->traces()->links() }}
    </div>
</x-filament-panels::page>
