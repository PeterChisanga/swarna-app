<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $table = 'sections';
    protected $primaryKey = 'section_id';

    protected $fillable = ['section_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class, 'section_id', 'section_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
