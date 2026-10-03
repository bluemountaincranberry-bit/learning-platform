<?php

namespace Database\Seeders;

use App\Modules\Learning\Application\LearningFlowDefaults;
use App\Modules\Learning\Domain\Models\LearningFlowProfile;
use Illuminate\Database\Seeder;

class LearningFlowProfileSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LearningFlowDefaults::recommendedProfiles() as $slug => $profile) {
            LearningFlowProfile::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $profile['name'], 'description' => $profile['description'], 'status' => 'published', 'version' => 1, 'config' => $profile['config'], 'published_at' => now()],
            );
        }
    }
}
