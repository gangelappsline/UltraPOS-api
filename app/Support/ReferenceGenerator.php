<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates unique, human friendly reference numbers per business
 * (e.g. SAL-20250115-00042 / PUR-20250115-00007).
 */
final class ReferenceGenerator
{
    /**
     * Build the next sequential reference for a table, scoped per business.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public static function generate(string $model, string $column, string $prefix, int $businessId): string
    {
        return DB::transaction(function () use ($model, $column, $prefix, $businessId): string {
            $date = now()->format('Ymd');
            $like = sprintf('%s-%s-%s-%', $prefix, $businessId, $date);

            /** @var string|null $last */
            $last = $model::query()
                ->where($column, 'like', $like.'%')
                ->orderByDesc($column)
                ->value($column);

            $sequence = $last === null ? 0 : (int) Str::afterLast($last, '-');
            $sequence++;

            do {
                $reference = sprintf('%s-%d-%s-%05d', $prefix, $businessId, $date, $sequence);
                $sequence++;
            } while ($model::withTrashed()->where($column, $reference)->exists());

            return $reference;
        });
    }
}
