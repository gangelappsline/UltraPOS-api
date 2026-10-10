<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products. Stock quantities are decimal(12,2) so weight/volume based
     * verticals (groceries, hardware) can sell fractional amounts.
     * "metadata" (json) carries vertical-specific attributes (e.g. active
     * ingredient for pharmacies, table/seat mapping for restaurants).
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->string('sku', 64);
            $table->string('barcode', 64)->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Percentage, e.g. 16.00');
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->decimal('max_stock', 12, 2)->nullable();
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->date('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'sku']);
            $table->index(['business_id', 'barcode']);
            $table->index(['business_id', 'is_active', 'is_featured']);
            $table->index(['business_id', 'category_id']);
            $table->index(['business_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
