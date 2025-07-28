<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreparationEntity extends Model
{
    protected $guarded = ['id'];

    public function entity()
    {
        return $this->morphTo();
    }

    public function preparation()
    {
        return $this->belongsTo(Preparation::class);
    }
}
