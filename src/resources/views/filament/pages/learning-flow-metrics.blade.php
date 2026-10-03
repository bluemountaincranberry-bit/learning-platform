<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->profiles() as $profile)
                @php($summary = $this->summary($profile->id))
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold">{{ $profile->name }}</h3>
                        <span class="text-xs text-gray-500">v{{ $profile->version }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">{{ $profile->description }}</p>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                        <div><strong class="block text-lg">{{ $summary['selections'] }}</strong><span class="text-gray-500">Selections</span></div>
                        <div><strong class="block text-lg">{{ $summary['outcomes'] }}</strong><span class="text-gray-500">Outcomes</span></div>
                        <div><strong class="block text-lg">{{ $summary['success_rate'] }}%</strong><span class="text-gray-500">Success</span></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="font-semibold">Synthetic learner simulation</h2>
            <p class="mt-1 text-sm text-gray-500">Expected activity selection for common learner states before publishing or assigning a profile.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left"><th class="px-3 py-2">Scenario</th><th class="px-3 py-2">Activity</th><th class="px-3 py-2">Dimension</th><th class="px-3 py-2">Reason</th></tr></thead>
                    <tbody>
                        @foreach ($this->simulation() as $row)
                            <tr class="border-b last:border-0"><td class="px-3 py-2">{{ $row['scenario'] }}</td><td class="px-3 py-2 font-medium">{{ $row['activity'] }}</td><td class="px-3 py-2">{{ $row['dimension'] }}</td><td class="px-3 py-2 text-gray-500">{{ $row['reason'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
