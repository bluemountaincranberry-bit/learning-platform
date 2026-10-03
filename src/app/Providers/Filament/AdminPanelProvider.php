<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AiChatOverviewWidget;
use App\Filament\Widgets\IngestionStatusOverview;
use App\Modules\User\Models\User;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                IngestionStatusOverview::class,
                AiChatOverviewWidget::class,
            ])
            ->navigationItems([
                // Plain link into the Vue canvas app's own home (prompt/agent
                // catalog) — GraphDefinitions/PromptTemplates below are
                // read-only lists that link into individual items; this is
                // the direct front door, listed first in the same group so
                // Filament stays the single starting point for admin work
                // instead of the two surfaces feeling unrelated.
                NavigationItem::make('Open AI Builder')
                    ->url('/admin/ai-builder')
                    ->icon('heroicon-o-rocket-launch')
                    ->group('AI Builder')
                    ->sort(-1)
                    ->visible(fn (): bool => (auth()->user() instanceof User) && auth()->user()->can('manage-ai-builder')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                Authorize::using('access-admin-panel'),
            ]);
    }
}
