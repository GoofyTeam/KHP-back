<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Room;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        $company = Company::factory();

        return [
            'label' => 'T-'.Str::padLeft((string) $this->faker->unique()->numberBetween(1, 999), 3, '0'),
            'seats' => $this->faker->numberBetween(2, 8),
            'company_id' => $company,
            'room_id' => Room::factory()->for($company),
        ];
    }
}
