<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuTypePublicOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_type_id',
        'company_id',
        'position',
    ];

    public function menuType(): BelongsTo
    {
        return $this->belongsTo(MenuType::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
