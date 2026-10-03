<x-filament-panels::page>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Graph</th>
                    <th class="px-4 py-3">Current node</th>
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">Completed</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Failure reason</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($this->runs() as $run)
                    <tr>
                        <td class="px-4 py-3 text-gray-500">{{ $run->id }}</td>
                        <td class="px-4 py-3">{{ $run->graph_name }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $run->current_node }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $run->started_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $run->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-filament::badge :color="match ($run->status) {
                                'failed' => 'danger',
                                'completed' => 'success',
                                'paused' => 'warning',
                                default => 'gray',
                            }">
                                {{ $run->status }}
                            </x-filament::badge>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $run->failure_reason ? Str::limit($run->failure_reason, 60) : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($this->canApproveAndApply($run))
                                {{ ($this->approveAndApplyAction())->arguments(['graphRunId' => $run->id]) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">No graph runs recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->runs()->links() }}
    </div>
</x-filament-panels::page>
