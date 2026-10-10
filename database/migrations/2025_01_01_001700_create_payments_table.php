<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Polymorphic payments (payable: Sale, Customer, Purchase...). */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method')->default(PaymentMethod::CASH->value);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->string('status')->default(TransactionStatus::COMPLETED->value);
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['business_id', 'status', 'paid_at']);
            $table->index(['business_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
