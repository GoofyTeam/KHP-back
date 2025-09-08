<?php

namespace Database\Factories;

use App\Models\SpecialQuickAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SpecialQuickAccess>
 */
class SpecialQuickAccessFactory extends Factory
{
    protected $model = SpecialQuickAccess::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'url' => '/'.$this->faker->slug(),
        ];
    }
}
