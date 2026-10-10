<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitType;
use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $abbreviation
 * @property UnitType $type
 */
class UnitOfMeasure extends Model
{
    /** @use HasFactory<\Database\Factories\UnitOfMeasureFactory> */
    use BelongsToBusiness;
    use HasFactory;

    protected $table = 'units_of_measure';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'abbreviation',
        'type',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UnitType::class,
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
