<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'name' => 'Salle '.$counter,
            'code' => 'R'.$counter,
            'company_id' => Company::factory(),
        ];
    }
}
