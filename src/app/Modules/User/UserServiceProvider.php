<?php

namespace App\Modules\User;

use App\Modules\User\Application\AnalysisCreatorPreferencesReader;
use App\Modules\User\Application\Contracts\AnalysisCreatorPreferencesReaderInterface;
use App\Modules\User\Application\Contracts\LearningFlowLearnerReaderInterface;
use App\Modules\User\Application\Contracts\LearningFlowPreferenceStoreInterface;
use App\Modules\User\Application\LearningFlowLearnerReader;
use App\Modules\User\Application\LearningFlowPreferenceStore;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnalysisCreatorPreferencesReaderInterface::class, AnalysisCreatorPreferencesReader::class);
        $this->app->bind(LearningFlowLearnerReaderInterface::class, LearningFlowLearnerReader::class);
        $this->app->bind(LearningFlowPreferenceStoreInterface::class, LearningFlowPreferenceStore::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/User/Routes/api.php'));
    }
}
