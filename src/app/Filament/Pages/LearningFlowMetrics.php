<?php

namespace App\Filament\Pages;

use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Learning\Domain\Models\LearningFlowProfile;
use App\Modules\User\Models\User;
use App\Modules\Learning\Application\AdaptiveActivitySelector;
use App\Modules\Learning\Application\LearningFlowMetricsService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LearningFlowMetrics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Learning flow metrics';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected string $view = 'filament.pages.learning-flow-metrics';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-learning-flows') ?? false;
    }

    /** @return Collection<int, LearningFlowProfile> */
    public function profiles(): Collection
    {
        return LearningFlowProfile::query()->where('status', 'published')->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    public function summary(?int $profileId): array
    {
        return app(LearningFlowMetricsService::class)->summary($profileId);
    }

    /** @return array<int, array{scenario: string, activity: string, dimension: string, reason: string}> */
    public function simulation(): array
    {
        $profile = $this->profiles()->first();
        if ($profile === null) {
            return [];
        }

        $selector = app(AdaptiveActivitySelector::class);
        $user = new User(['current_level' => 'B1', 'learning_goal' => 'general']);
        $lexeme = new ContentLexeme(['text' => 'practice']);
        $scenarios = [
            ['scenario' => 'New word', 'confidence' => ['recognition' => 0, 'recall' => 0, 'production' => 0, 'listening' => 0, 'speaking' => 0], 'attempts' => 0],
            ['scenario' => 'Weak listening', 'confidence' => ['recognition' => 80, 'recall' => 75, 'production' => 70, 'listening' => 25, 'speaking' => 60], 'recent_error' => 'could_not_hear', 'attempts' => 4],
            ['scenario' => 'Weak speaking', 'confidence' => ['recognition' => 80, 'recall' => 75, 'production' => 70, 'listening' => 70, 'speaking' => 20], 'recent_error' => 'pronunciation_error', 'attempts' => 4],
            ['scenario' => 'Weak recall', 'confidence' => ['recognition' => 75, 'recall' => 20, 'production' => 45, 'listening' => 60, 'speaking' => 50], 'recent_error' => 'unknown_meaning', 'attempts' => 3],
        ];

        return collect($scenarios)->map(function (array $scenario) use ($selector, $profile, $user, $lexeme): array {
            $result = $selector->choose($lexeme, $user, $profile->config, [...$scenario, 'has_example' => true, 'has_translation' => true]);

            return ['scenario' => $scenario['scenario'], 'activity' => $result['activity'], 'dimension' => $result['target_dimension'], 'reason' => $result['reason']];
        })->all();
    }
}
