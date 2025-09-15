<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Room;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'label' => 'T'.uniqid(),
            'seats' => 4,
            'room_id' => Room::factory(),
            'company_id' => Company::factory(),
        ];
    }
}
