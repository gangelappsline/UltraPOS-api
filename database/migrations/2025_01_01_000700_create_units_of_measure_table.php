<?php

declare(strict_types=1);

use App\Enums\UnitType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Units of measure (piece, kg, litre, m...) scoped per tenant. */
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('abbreviation', 16);
            $table->string('type')->default(UnitType::UNIT->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'abbreviation']);
            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
