<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'nama',
        'username',
        'password',
        'role',
        'must_change_password',
        'current_session_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'current_session_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPeserta(): bool
    {
        return $this->role === 'peserta';
    }

    public function wajibGantiPassword(): bool
    {
        return $this->isPeserta() && (bool) $this->must_change_password;
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function createdSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class, 'created_by');
    }
}
