<?php

declare(strict_types=1);

use App\Enums\BillingCycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** SaaS subscription plans (global, not tenant-scoped). */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('billing_cycle')->default(BillingCycle::MONTHLY->value);
            $table->json('features')->nullable();
            $table->json('limits')->nullable()->comment('e.g. {"users":5,"products":500}');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
