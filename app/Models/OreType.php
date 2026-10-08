<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OreType extends Model
{
    protected $table = 'crushing_ore_types';
    protected $primaryKey = 'ore_type_id';
    protected $fillable = ['ore_type', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
