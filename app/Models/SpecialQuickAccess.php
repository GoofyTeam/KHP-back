<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialQuickAccess extends Model
{
    use HasFactory;

    protected $table = 'special_quick_accesses';

    protected $guarded = [
        'id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
