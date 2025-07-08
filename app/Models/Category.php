<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    public $timestamps = true;

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'category_ingredient')
            ->withTimestamps();
    }
}
