<?php

namespace Database\Factories;

use App\Models\CompanyBusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyBusinessHour>
 */
class CompanyBusinessHourFactory extends Factory
{
    protected $model = CompanyBusinessHour::class;

    public function definition(): array
    {
        $day = $this->faker->numberBetween(1, 7);
        $openHour = $this->faker->numberBetween(6, 20);
        $openMinute = $this->faker->randomElement([0, 15, 30, 45]);
        $duration = $this->faker->numberBetween(1, 6);
        $closeHour = ($openHour + $duration) % 24;
        $isOvernight = $openHour + $duration >= 24;

        if (! $isOvernight && $closeHour === 0) {
            $closeHour = 23;
            $closeMinute = 59;
        } else {
            $closeMinute = $this->faker->randomElement([0, 15, 30, 45]);
        }

        $opensAt = sprintf('%02d:%02d', $openHour, $openMinute);
        $closesAt = sprintf('%02d:%02d', $closeHour, $closeMinute);

        if ($isOvernight && $closeHour === 0 && $closeMinute === 0) {
            $closesAt = '00:00';
        }

        return [
            'company_id' => null,
            'day_of_week' => $day,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'is_overnight' => $isOvernight,
            'sequence' => 1,
        ];
    }
}
