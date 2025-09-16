<?php

namespace Tests\Unit;

use App\Models\Menu;
use App\Models\StepMenu;
use Tests\TestCase;

class StepMenuTest extends TestCase
{
    public function test_total_price_multiplies_menu_price_by_quantity(): void
    {
        $stepMenu = StepMenu::make(['quantity' => 3]);
        $stepMenu->setRelation('menu', Menu::make(['price' => 9.5]));

        $this->assertSame(28.5, $stepMenu->totalPrice());
    }

    public function test_total_price_is_zero_when_menu_is_missing(): void
    {
        $stepMenu = StepMenu::make(['quantity' => 3]);

        $this->assertSame(0.0, $stepMenu->totalPrice());
    }
}
