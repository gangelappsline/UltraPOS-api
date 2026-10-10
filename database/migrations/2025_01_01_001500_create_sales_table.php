<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Seller who rang the sale');
            $table->string('reference_number', 40)->unique();
            $table->string('status')->default(SaleStatus::PENDING->value);
            $table->string('payment_status')->default(PaymentStatus::UNPAID->value);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('sale_type')->default(SaleType::POS->value);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['business_id', 'user_id']);
            $table->index(['business_id', 'customer_id']);
            $table->index(['business_id', 'sale_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
