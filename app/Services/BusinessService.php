<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Tenant lifecycle and per-tenant settings management.
 */
class BusinessService
{
    /**
     * Paginated tenant list. Admins see everything; owners see their own.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Business::query()->with('owner')->withCount('users');

        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user): void {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('users', fn ($uq) => $uq->whereKey($user->id));
            });
        }

        $query
            ->when($filters['search'] ?? null, function ($q, string $term): void {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($qq) use ($like): void {
                    $qq->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(legal_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(tax_id, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($filters['business_type'] ?? null, fn ($q, string $type) => $q->where('business_type', $type))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']));

        return $query->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    /**
     * Create a tenant (admin flow) + attach an owner when provided.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Business
    {
        return DB::transaction(function () use ($actor, $data): Business {
            $business = Business::create([
                ...collect($data)->except('owner_id')->all(),
                'owner_id' => isset($data['owner_id']) ? (int) $data['owner_id'] : $actor->id,
            ]);

            $owner = $business->owner ?? $actor;
            $business->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'business', 'joined_at' => now()],
            ]);

            return $business;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Business $business, array $data): Business
    {
        $business->update($data);

        return $business->refresh();
    }

    /** Soft-delete a tenant. */
    public function delete(Business $business): void
    {
        $business->delete();
    }

    /** Toggle tenant status (admin). */
    public function toggleStatus(Business $business): Business
    {
        $business->update(['is_active' => ! $business->is_active]);

        return $business->refresh();
    }

    /**
     * All settings of a tenant grouped by "group".
     *
     * @return array<string, array<string, mixed>>
     */
    public function settings(Business $business): array
    {
        return Setting::query()
            ->where('business_id', $business->id)
            ->get()
            ->groupBy('group')
            ->map(fn ($group) => $group->pluck('value', 'key')->all())
            ->all();
    }

    /**
     * Bulk upsert settings.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    public function updateSettings(Business $business, array $settings, string $group = 'general'): array
    {
        DB::transaction(function () use ($business, $settings, $group): void {
            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(
                    ['business_id' => $business->id, 'key' => (string) $key],
                    ['value' => $value, 'group' => $group],
                );
            }
        });

        return $this->settings($business);
    }
}
