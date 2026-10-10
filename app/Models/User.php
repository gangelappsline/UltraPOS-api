<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * System user. Tenancy is resolved through the business_user pivot;
 * admins are global (no membership required).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property-read int|null $active_business_id
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Memoized active business for the current request.
     */
    private ?Business $activeBusinessCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /* ─────────────────────────── Relations ─────────────────────────── */

    /** Businesses this user belongs to, with contextual pivot role. */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /** Businesses owned (founded) by this user. */
    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    /* ─────────────────────────── Tenancy ───────────────────────────── */

    /**
     * The tenant context of this user: the first ACTIVE business they
     * belong to (owner first). Null for global admins.
     */
    public function activeBusiness(): ?Business
    {
        if ($this->activeBusinessCache !== null) {
            return $this->activeBusinessCache;
        }

        return $this->activeBusinessCache = $this->businesses()
            ->where('businesses.is_active', true)
            ->orderByRaw('CASE WHEN businesses.owner_id = ? THEN 0 ELSE 1 END', [$this->getKey()])
            ->first();
    }

    /** Convenience attribute used by the global tenant scope. */
    public function getActiveBusinessIdAttribute(): ?int
    {
        return $this->activeBusiness()?->id;
    }

    /** Reset memoized tenant (e.g. after membership changes). */
    public function flushBusinessCache(): void
    {
        $this->activeBusinessCache = null;
    }

    /** Whether the user is a member (any role) of the given business. */
    public function belongsToBusiness(int $businessId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->businesses()->whereKey($businessId)->exists()
            || $this->ownedBusinesses()->whereKey($businessId)->exists();
    }

    /* ─────────────────────────── Role helpers ──────────────────────── */

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN->value);
    }

    public function isBusinessOwner(): bool
    {
        return $this->hasRole(UserRole::BUSINESS->value);
    }

    public function isSeller(): bool
    {
        return $this->hasRole(UserRole::SELLER->value);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(UserRole::CUSTOMER->value);
    }

    /**
     * Passport scopes derived from the user's roles + fine-grained
     * operational scopes (used when issuing tokens).
     *
     * @return array<int, string>
     */
    public function tokenScopes(): array
    {
        $scopes = $this->getRoleNames()
            ->map(fn (string $role): string => $role)
            ->all();

        if ($this->isAdmin() || $this->isBusinessOwner()) {
            $scopes = array_merge($scopes, [
                'products.read', 'products.write', 'sales.read', 'sales.create',
                'inventory.manage', 'reports.read',
            ]);
        }

        if ($this->isSeller()) {
            $scopes = array_merge($scopes, [
                'products.read', 'sales.read', 'sales.create', 'inventory.manage',
            ]);
        }

        return array_values(array_unique($scopes));
    }

    /* ─────────────────────────── Scopes ────────────────────────────── */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /* ─────────────────────── Notifications ─────────────────────────── */

    /** Route password resets through the custom queued mailable. */
    public function sendPasswordResetNotification(string $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** Route email verification through the custom queued mailable. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
}
