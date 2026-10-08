<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stream extends Model
{
    protected $table = 'streams';
    protected $primaryKey = 'stream_id';

    protected $fillable = ['section_id', 'stream_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Streams belonging to a section, e.g. Stream::forSection('Crushing'). */
    public function scopeForSection(Builder $q, string $sectionName): Builder
    {
        return $q->whereHas('section', fn (Builder $s) => $s->where('section_name', $sectionName));
    }
}
