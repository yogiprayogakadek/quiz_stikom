<?php

namespace App\Models;

use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

    protected $fillable = [
        'nama',
        'username',
        'email',
        'password',
        'role',
        'is_platform_admin',
        'must_change_password',
        'tour_completed_at',
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
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
            'is_platform_admin' => 'boolean',
            'tour_completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (! Schema::hasTable('organizations') || Organization::query()->count() !== 1) {
                return;
            }

            $organizationId = Organization::query()->value('id');
            $user->organizations()->syncWithoutDetaching([
                $organizationId => [
                    'role' => $user->isAdmin() ? 'admin' : 'participant',
                    'status' => 'active',
                ],
            ]);
        });

        static::updating(function (User $user) {
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasVerifiedEmail(): bool
    {
        return blank($this->email) || ! is_null($this->email_verified_at);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->isAdmin() && (bool) $this->is_platform_admin;
    }

    public function isCurrentOrganizationOwner(): bool
    {
        $organizationId = app(OrganizationContext::class)->id();

        return $organizationId !== null
            && $this->organizations()
                ->whereKey($organizationId)
                ->wherePivot('role', 'owner')
                ->wherePivot('status', 'active')
                ->exists();
    }

    public function isPeserta(): bool
    {
        return $this->role === 'peserta';
    }

    public function wajibGantiPassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function scopeInCurrentOrganization(Builder $query): Builder
    {
        $organizationId = app(OrganizationContext::class)->id();

        return $organizationId === null
            ? $query
            : $query->whereHas('organizations', fn (Builder $organizationQuery) => $organizationQuery
                ->whereKey($organizationId)
                ->where('organization_memberships.status', 'active'));
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_user')->withTimestamps();
    }

    public function createdSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class, 'created_by');
    }
}
