<?php

namespace App\Models;

use App\Enums\StepMenuStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Menu|null $menu
 * @property int $quantity
 */
class StepMenu extends Model
{
    /** @use HasFactory<\Database\Factories\StepMenuFactory> */
    use HasFactory;

    protected $fillable = [
        'order_step_id',
        'menu_id',
        'quantity',
        'status',
        'note',
        'served_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'status' => StepMenuStatus::class,
        'served_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(OrderStep::class, 'order_step_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
