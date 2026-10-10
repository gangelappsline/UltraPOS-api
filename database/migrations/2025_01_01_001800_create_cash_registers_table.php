<?php

declare(strict_types=1);

use App\Enums\CashRegisterStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One open session per user (enforced at service layer + index). */
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->decimal('closing_amount', 12, 2)->nullable();
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->string('status')->default(CashRegisterStatus::OPEN->value);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'user_id', 'status']);
            $table->index(['business_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
