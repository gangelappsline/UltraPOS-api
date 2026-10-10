<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** POS customers (walk-in or linked to a registered user account). */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('tax_id', 64)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0)->comment('Amount owed by the customer');
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'email']);
            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
