<?php

namespace Database\Seeders;

use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Menu;
use App\Models\OrderStep;
use App\Models\StepMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class StepMenuSeeder extends Seeder
{
    private const SAMPLE_NOTES = [
        'Allergie : retirer les noisettes.',
        'Cuisson demandée : saignant.',
        'Servir la sauce à part.',
        'Sans lactose : utiliser du lait végétal.',
        'Ajouter une assiette bien chaude.',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menusByCompany = Menu::query()->get()->groupBy('company_id');

        $steps = OrderStep::query()
            ->with('order')
            ->withCount('stepMenus')
            ->get();

        foreach ($steps as $step) {
            if ($step->step_menus_count > 0) {
                continue;
            }

            $menus = $menusByCompany[$step->order->company_id] ?? collect();

            if ($menus->isEmpty()) {
                continue;
            }

            $quantity = random_int(1, min(3, $menus->count()));

            $menus->shuffle()
                ->take($quantity)
                ->each(function ($menu) use ($step): void {
                    $status = $this->statusForStep($step->status);
                    $servedAt = $status === StepMenuStatus::SERVED
                        ? now()->subMinutes(random_int(1, 45))
                        : null;

                    StepMenu::query()->create([
                        'order_step_id' => $step->id,
                        'menu_id' => $menu->id,
                        'quantity' => random_int(1, 4),
                        'status' => $status,
                        'note' => $this->randomNote(),
                        'served_at' => $servedAt,
                    ]);
                });
        }
    }

    private function statusForStep(OrderStepStatus $status): StepMenuStatus
    {
        $allowed = match ($status) {
            OrderStepStatus::IN_PREP => [StepMenuStatus::IN_PREP],
            OrderStepStatus::READY => [StepMenuStatus::IN_PREP, StepMenuStatus::READY],
            OrderStepStatus::SERVED => [StepMenuStatus::READY, StepMenuStatus::SERVED],
        };

        return Arr::random($allowed);
    }

    private function randomNote(): ?string
    {
        if (random_int(1, 10) > 3) {
            return null;
        }

        return Arr::random(self::SAMPLE_NOTES);
    }
}
