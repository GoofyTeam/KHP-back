<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Menu $menu
 */
class MenuOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'status',
        'quantity',
    ];

    public $timestamps = true;

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
