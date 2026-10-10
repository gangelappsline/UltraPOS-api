<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Line items with price snapshots (immutable history). */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->comment('Snapshot at sale time');
            $table->decimal('unit_price', 12, 2)->comment('Snapshot at sale time');
            $table->decimal('quantity', 12, 2);
            $table->decimal('discount', 12, 2)->default(0)->comment('Absolute discount on the line');
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0)->comment('quantity * unit_price - discount');
            $table->decimal('total', 12, 2)->default(0)->comment('subtotal + tax');
            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
