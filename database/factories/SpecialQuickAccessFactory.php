<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SpecialQuickAccess;
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
            'company_id' => Company::factory(),
            'name' => $this->faker->words(2, true),
            'url_key' => 'special_action',
        ];
    }
}
