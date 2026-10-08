<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    /** Full name, so existing code that reads $user->name keeps working. */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn () => trim($this->first_name . ' ' . $this->last_name)
        );
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    /** Production opens/closes shift reports. Admin is allowed too (see README, decision 1). */
    public function canEnterShift(): bool
    {
        return in_array($this->role, ['Production', 'Admin'], true);
    }

    /** Lab captures moisture samples. Admin is allowed too (see README, decision 1). */
    public function canEnterMoisture(): bool
    {
        return in_array($this->role, ['Lab', 'Admin'], true);
    }

    public function initials(): string
    {
        return mb_strtoupper(
            mb_substr((string) $this->first_name, 0, 1) .
            mb_substr((string) $this->last_name, 0, 1)
        );
    }
}
