<?php

namespace Database\Factories;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Content\Domain\Models\Content>
 */
class ContentFactory extends Factory
{
    protected $model = \App\Modules\Content\Domain\Models\Content::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'type' => 'youtube',
            'language' => 'en',
            'level' => null,
            'origin' => 'curated',
            'status' => 'ready',
            'source_url' => null,
            'created_by' => User::factory(),
        ];
    }
}
