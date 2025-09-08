<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickAccess extends Model
{
    use HasFactory;

    protected $table = 'quick_accesses';

    protected $guarded = [
        'id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
