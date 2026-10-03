<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Modules\Ai\AiServiceProvider::class,
    App\Modules\Content\ContentServiceProvider::class,
    App\Modules\Learning\LearningServiceProvider::class,
    App\Modules\Srs\SrsServiceProvider::class,
    App\Modules\User\UserServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
];
