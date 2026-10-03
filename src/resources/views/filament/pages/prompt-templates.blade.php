<x-filament-panels::page>
    <div class="mb-4">
        <a href="/admin/ai-builder" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:underline">
            &larr; Full prompt catalog (grouped by real app flow: agent chat, content ingestion, student practice)
        </a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Key</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Versions</th>
                    <th class="px-4 py-3">Active version</th>
                    <th class="px-4 py-3">System prompt preview</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($this->templates() as $template)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $template->key }}</td>
                        <td class="px-4 py-3">{{ $template->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $template->versions_count }}</td>
                        <td class="px-4 py-3">
                            @if ($template->activeVersion)
                                <x-filament::badge color="success">v{{ $template->activeVersion->version }}</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">none (code default in use)</x-filament::badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $template->activeVersion ? Str::limit($template->activeVersion->system_template, 80) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="/admin/ai-builder/prompts/{{ $template->key }}" class="text-primary-600 hover:underline">Open in builder</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No prompt template overrides yet — every AI call is using its code default.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
