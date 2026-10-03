<?php

namespace App\Filament\Widgets;

use App\Modules\Ai\Domain\Models\AiConversation;
use App\Support\AiConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiChatOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        if (! AiConfig::isEnabled()) {
            return [];
        }

        return [
            Stat::make('AI conversations', (string) AiConversation::query()->count()),
        ];
    }
}
