<?php

namespace App\Filament\Widgets;

use App\Modules\Content\Domain\Models\Content;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IngestionStatusOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending moderation', (string) Content::query()
                ->where('origin', 'user-submitted')
                ->where('status', 'pending')
                ->count()),
            Stat::make('Processing', (string) Content::query()->where('status', 'processing')->count()),
            Stat::make('Failed / Rejected', (string) Content::query()->whereIn('status', ['failed', 'rejected'])->count()),
            Stat::make('Ready', (string) Content::query()->where('status', 'ready')->count()),
        ];
    }
}
