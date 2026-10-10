<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property string $category
 * @property float $amount
 * @property Carbon $expense_date
 */
class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'user_id',
        'category',
        'description',
        'amount',
        'expense_date',
        'receipt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $query, Carbon|string|null $from, Carbon|string|null $to): Builder
    {
        $from = $from instanceof Carbon ? $from : ($from ? Carbon::parse($from) : null);
        $to = $to instanceof Carbon ? $to : ($to ? Carbon::parse($to) : null);

        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('expense_date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $q) => $q->whereDate('expense_date', '<=', $to->toDateString()));
    }
}
