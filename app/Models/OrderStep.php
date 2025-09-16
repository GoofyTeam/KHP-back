<?php

namespace App\Models;

use App\Enums\OrderStepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderStep extends Model
{
    /** @use HasFactory<\Database\Factories\OrderStepFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'name',
        'position',
        'status',
        'served_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'status' => OrderStepStatus::class,
        'served_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stepMenus(): HasMany
    {
        return $this->hasMany(StepMenu::class);
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'step_menus', 'order_step_id', 'menu_id')
            ->withPivot('quantity', 'status', 'note', 'served_at')
            ->withTimestamps();
    }
}
