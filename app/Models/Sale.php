<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Sale document. Totals are ALWAYS computed server-side by SaleService.
 *
 * @property int $id
 * @property int $business_id
 * @property string $reference_number
 * @property SaleStatus $status
 * @property PaymentStatus $payment_status
 * @property SaleType $sale_type
 * @property float $subtotal
 * @property float $tax
 * @property float $discount
 * @property float $total
 * @property float $paid_amount
 * @property float $change_amount
 * @property Carbon|null $completed_at
 */
class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'customer_id',
        'user_id',
        'reference_number',
        'status',
        'payment_status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'paid_amount',
        'change_amount',
        'notes',
        'sale_type',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_status' => PaymentStatus::class,
            'sale_type' => SaleType::class,
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /* ─────────────────────────── Relations ─────────────────────────── */

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function completedPayments(): MorphMany
    {
        return $this->payments()->where('status', 'completed');
    }

    /* ─────────────────────── Business helpers ──────────────────────── */

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID;
    }

    public function canBeCancelled(): bool
    {
        return ! $this->status->isFinal();
    }

    /** Recompute payment_status from paid_amount vs total. */
    public function resolvePaymentStatus(): PaymentStatus
    {
        $paid = (float) $this->paid_amount;
        $total = (float) $this->total;

        return match (true) {
            $paid <= 0.0 => PaymentStatus::UNPAID,
            $paid + 0.005 >= $total => PaymentStatus::PAID,
            default => PaymentStatus::PARTIAL,
        };
    }

    /* ─────────────────────────── Scopes ────────────────────────────── */

    public function scopeStatus(Builder $query, SaleStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof SaleStatus ? $status->value : $status);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::COMPLETED->value);
    }

    public function scopeBetween(Builder $query, Carbon|string|null $from, Carbon|string|null $to): Builder
    {
        $from = $from instanceof Carbon ? $from : ($from ? Carbon::parse($from) : null);
        $to = $to instanceof Carbon ? $to : ($to ? Carbon::parse($to) : null);

        return $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to->endOfDay()));
    }
}
