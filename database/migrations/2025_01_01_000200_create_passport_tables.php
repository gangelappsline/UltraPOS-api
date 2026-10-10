<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel Passport 13 OAuth2 tables.
 *
 * This is a vendored copy of Passport's own published migrations
 * (`vendor:publish --tag=passport-migrations`), merged into one file so a
 * fresh install only needs `php artisan migrate`.
 *
 * Do NOT run `php artisan vendor:publish --tag=passport-migrations` in this
 * project: the tables already exist here and publishing them again makes the
 * next `migrate` fail with "Base table or view already exists".
 *
 * Passport 13 stores client ids as UUIDs (Laravel\Passport\Client uses the
 * HasUuids trait), `redirect_uris`/`grant_types` as JSON text and no longer
 * has an `oauth_personal_access_clients` table — keep this schema in sync
 * when upgrading Passport.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            $table->boolean('revoked');
            $table->timestamps();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table): void {
            $table->char('id', 80)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignUuid('client_id');
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
            $table->char('id', 80)->primary();
            $table->char('access_token_id', 80)->index();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_auth_codes', function (Blueprint $table): void {
            $table->char('id', 80)->primary();
            $table->foreignId('user_id')->index();
            $table->foreignUuid('client_id');
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_device_codes', function (Blueprint $table): void {
            $table->char('id', 80)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignUuid('client_id')->index();
            $table->char('user_code', 8)->unique();
            $table->text('scopes');
            $table->boolean('revoked');
            $table->dateTime('user_approved_at')->nullable();
            $table->dateTime('last_polled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_device_codes');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_clients');
    }

    /**
     * Passport keeps its tables on its own configured connection.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
