<?php

namespace Database\Factories;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Ai\Domain\Models\AiConversation>
 */
class AiConversationFactory extends Factory
{
    protected $model = \App\Modules\Ai\Domain\Models\AiConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => null,
        ];
    }
}
