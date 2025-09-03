<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LossReason;
use Illuminate\Database\Eloquent\Factories\Factory;

class LossReasonFactory extends Factory
{
    protected $model = LossReason::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'company_id' => Company::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
