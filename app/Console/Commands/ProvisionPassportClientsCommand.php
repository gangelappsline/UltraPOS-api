<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * ultrapos:passport-clients — provision the OAuth2 clients UltraPOS needs.
 *
 * Replaces `php artisan passport:install`, which additionally publishes
 * Passport's own migrations. This project already ships them (see
 * database/migrations/2025_01_01_000200_create_passport_tables.php) and
 * publishing a second copy makes the next `migrate` fail with
 * "Base table or view already exists".
 *
 * Idempotent: existing clients are reused.
 */
class ProvisionPassportClientsCommand extends Command
{
    protected $signature = 'ultrapos:passport-clients
                            {--keys : Generate the Passport RSA keys first (skipped when they already exist)}
                            {--force : Recreate the clients even when they already exist}';

    protected $description = 'Create the Passport personal-access and password-grant clients';

    private const PERSONAL_ACCESS_CLIENT_NAME = 'UltraPOS Personal Access Client';

    private const PASSWORD_CLIENT_NAME = 'UltraPOS Password Grant Client';

    public function handle(ClientRepository $clients): int
    {
        if (! $this->keysExist()) {
            // Required before the first token is issued; seeding never prompts.
            $this->call('passport:keys');
        } elseif ($this->option('keys')) {
            $this->call('passport:keys', ['--force' => true]);
        }

        $table = Passport::client()->getTable();

        if (! Schema::hasTable($table)) {
            $this->error("Table [{$table}] does not exist — run `php artisan migrate` first.");

            return self::FAILURE;
        }

        $provider = (string) config('auth.guards.api.provider', 'users');
        $grantTypes = $this->grantTypes();

        // ── Personal access client (used by /api/v1/auth/login + register) ──
        $personal = $this->option('force') ? null : $this->findClient('personal_access');

        if ($personal === null) {
            $personal = $clients->createPersonalAccessGrantClient(self::PERSONAL_ACCESS_CLIENT_NAME, $provider);
            $this->info('✔ Personal access client created.');
        } else {
            $this->line('• Personal access client already present, reusing it.');
        }

        $this->table(['client', 'id', 'secret'], [[
            'personal access',
            (string) $personal->getKey(),
            (string) ($personal->plainSecret ?? '***shown only when created***'),
        ]]);

        // ── Password grant client (POST /oauth/token, grant_type=password) ──
        if (in_array('password', $grantTypes, true)) {
            $password = $this->option('force') ? null : $this->findClient('password');

            if ($password === null) {
                /** @var Client $password */
                $password = $clients->createPasswordGrantClient(self::PASSWORD_CLIENT_NAME, $provider, true);
                $this->info('✔ Password grant client created.');
            } else {
                $this->line('• Password grant client already present, reusing it.');
            }

            $this->table(['client', 'id', 'secret'], [[
                'password grant',
                (string) $password->getKey(),
                (string) ($password->plainSecret ?? '***shown only when created***'),
            ]]);

            $this->comment('Store the password-grant secret; it is not recoverable (it is hashed at rest).');
        }

        return self::SUCCESS;
    }

    /** @return list<string> the grant types the app accepts */
    private function grantTypes(): array
    {
        return config('ultrapos.password_grant_enabled', true)
            ? ['personal_access', 'password']
            : ['personal_access'];
    }

    private function findClient(string $grantType): ?Client
    {
        return Passport::client()
            ->newQuery()
            ->where('revoked', false)
            ->get()
            ->first(static fn (Client $client): bool => $client->hasGrantType($grantType));
    }

    private function keysExist(): bool
    {
        return is_file(Passport::keyPath('oauth-private.key'))
            && is_file(Passport::keyPath('oauth-public.key'));
    }
}
