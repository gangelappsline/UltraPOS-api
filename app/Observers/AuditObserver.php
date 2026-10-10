<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Automatic audit trail for key models (registered in AppServiceProvider).
 *
 * Records who did what, when, from where — with old/new value diffs.
 * Sensitive attributes (passwords, tokens) are redacted.
 */
class AuditObserver
{
    /** Attributes never persisted into the audit trail. */
    private const array REDACTED = [
        'password', 'remember_token', 'token', 'secret',
        'current_password', 'password_confirmation',
    ];

    public function created(Model $model): void
    {
        $this->record($model, AuditAction::CREATED, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->record($model, AuditAction::UPDATED, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        // Soft deletes land here too (deleted_at is part of the changes).
        $this->record($model, AuditAction::DELETED, $model->getOriginal(), ['deleted_at' => now()->toIso8601String()]);
    }

    public function restored(Model $model): void
    {
        $this->record($model, AuditAction::RESTORED, null, ['id' => $model->getKey()]);
    }

    public function forceDeleted(Model $model): void
    {
        $this->record($model, AuditAction::FORCE_DELETED, $model->getOriginal(), null);
    }

    private function record(Model $model, AuditAction $action, ?array $old, ?array $new): void
    {
        if ($model instanceof AuditLog) {
            return; // never audit the audit trail
        }

        try {
            // TenantContext is console-safe (auth('api') would force the
            // Passport guard — and its RSA keys — during migrations/seeding).
            $user = TenantContext::user();

            AuditLog::create([
                'business_id' => $this->resolveBusinessId($model, $user),
                'user_id' => $user?->id,
                'action' => $action->value,
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'old_values' => $this->clean($old),
                'new_values' => $this->clean($new),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the main business flow.
            Log::warning('AuditObserver failed: '.$e->getMessage(), [
                'model' => $model::class,
                'action' => $action->value,
            ]);
        }
    }

    private function resolveBusinessId(Model $model, ?User $user): ?int
    {
        if (isset($model->business_id)) {
            return (int) $model->business_id;
        }

        if ($model instanceof User) {
            return $model->active_business_id;
        }

        return $user?->active_business_id;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = [];

        foreach ($values as $key => $value) {
            $clean[$key] = in_array($key, self::REDACTED, true)
                ? '***REDACTED***'
                : $value;
        }

        return $clean;
    }
}
