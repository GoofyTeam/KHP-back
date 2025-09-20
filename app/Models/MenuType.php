<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read MenuTypePublicOrder|null $publicOrder
 */
class MenuType extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
    ];

    protected $with = ['publicOrder'];

    protected $appends = ['public_index'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function publicOrder(): HasOne
    {
        return $this->hasOne(MenuTypePublicOrder::class);
    }

    public function getPublicIndexAttribute(): int
    {
        /** @var MenuTypePublicOrder|null $publicOrder */
        $publicOrder = $this->publicOrder;

        return $publicOrder ? $publicOrder->position : 0;
    }

    public function scopeForCompany(Builder $query): Builder
    {
        $companyId = auth()->user()?->company_id;

        if (! $companyId) {
            return $query;
        }

        return $query->where('menu_types.company_id', $companyId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->leftJoin('menu_type_public_orders as mtpo', function ($join) {
                $join->on('mtpo.menu_type_id', '=', 'menu_types.id')
                    ->on('mtpo.company_id', '=', 'menu_types.company_id');
            })
            ->select('menu_types.*')
            ->orderByRaw('COALESCE(mtpo.position, 2147483647)')
            ->orderBy('menu_types.id');
    }
}
