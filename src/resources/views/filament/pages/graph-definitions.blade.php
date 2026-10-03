<x-filament-panels::page>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Key</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Versions</th>
                    <th class="px-4 py-3">Active version</th>
                    <th class="px-4 py-3">Nodes</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($this->definitions() as $definition)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $definition->key }}</td>
                        <td class="px-4 py-3">{{ $definition->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $definition->versions_count }}</td>
                        <td class="px-4 py-3">
                            @if ($definition->activeVersion)
                                <x-filament::badge color="success">v{{ $definition->activeVersion->version }}</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">none (code default in use)</x-filament::badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $definition->activeVersion ? count($definition->activeVersion->nodes) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="/admin/ai-builder/graphs/{{ $definition->key }}" class="text-primary-600 hover:underline">Open in canvas</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No graph definition overrides yet — every graph is using its hand-built code definition.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (count($this->graphNamesWithoutOverride()) > 0)
        <div class="mt-6">
            <h3 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Graphs running from code (no override yet)</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->graphNamesWithoutOverride() as $graphName)
                    <a href="/admin/ai-builder/graphs/{{ $graphName }}" class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-primary-600 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                        {{ $graphName }} — open in canvas
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
