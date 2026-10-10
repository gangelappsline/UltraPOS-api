# UltraPOS API

Multi-tenant Point-of-Sale REST API built with **Laravel 12**, **PHP 8.3+**, **Laravel Passport** (OAuth2), **MySQL 8**, **Redis** and **L5-Swagger** (OpenAPI 3).

Every tenant is a `Business`. Tenancy is enforced by the `business_user` pivot plus global model scopes, so a user can **never** read or write another tenant's data.

- **API base:** `/api/v1`
- **Swagger UI:** `/api/documentation`
- **OpenAPI JSON:** `/api/documentation.json`
- **Health check:** `GET /api/health`

---

## 1. Requirements

| Tool | Version / notes |
|------|-----------------|
| PHP | **8.3+** with `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer` (`intl`, `gd`, `zip`, `opcache`, `redis` recommended — `curl` ext is required by the framework too) |
| Composer | 2.x |
| MySQL | 8.x (`utf8mb4` / `utf8mb4_unicode_ci`) |
| Redis | 6+ — queues/cache/sessions. Optional: set `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION` to `database` if you do not run Redis |
| Node | not required — this project is API-only |

> **PHP check:** `php -m | grep -E "bcmath|openssl|pdo_mysql|mbstring|fileinfo"` — a missing `bcmath`
> breaks `Money`, a missing `openssl` breaks `passport:keys`.

## 2. Quick start (local)

```bash
git clone <repo-url> ultrapos-api && cd ultrapos-api

composer install
# No composer.lock is committed yet: use `composer update` once (it creates the
# lock file) if `composer install` complains "No lock file is present".
cp .env.example .env

php artisan key:generate            # APP_KEY
php artisan passport:keys           # storage/oauth-private.key + oauth-public.key

# Create the schema the .env points at, e.g.:
#   mysql -u root -e "CREATE DATABASE ultrapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed          # roles, permissions, plans, admin, demo tenant
                                    # + the Passport OAuth clients (see below)

php artisan l5-swagger:generate     # writes storage/api-docs/api-docs.json
php artisan serve                   # http://localhost:8000

# second terminal, for queued mails/notifications
php artisan queue:work redis --tries=3
```

`migrate --seed` provisions the two Passport clients this API needs (it runs
`ultrapos:passport-clients`), so there is **no `passport:install` step** — that
command would also publish Passport's migrations a second time and the next
`migrate` would die with *"Table 'oauth_auth_codes' already exists"*.

**Verify the install:**

```bash
curl -s http://localhost:8000/api/health | jq .
# {"success":true,"message":"All systems operational",
#  "data":{"status":"ok","checks":{"database":"ok","cache":"ok"},...}}
# 503 + checks.*="failing" tells you which dependency (MySQL / the CACHE_STORE,
# e.g. Redis not running) is unreachable.

curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"demo@ultrapos.test","password":"ChangeMe123!","device_name":"curl"}' | jq .data.token
# {"access_token":"...","token_type":"Bearer","scopes":["business"],"expires_at":"..."}

php artisan test                    # 5 feature suites + 1 unit suite
```

Default seeded accounts (change them!):

| Role | Email | Password |
|------|-------|----------|
| Global admin | `admin@ultrapos.test` | `ChangeMe123!` |
| Business owner (demo) | `demo@ultrapos.test` | `ChangeMe123!` |
| Seller (demo) | `seller@ultrapos.test` | `ChangeMe123!` |

## 3. Docker

```bash
cp .env.example .env          # required: the stack bind-mounts the project
docker compose up -d --build
docker compose exec app php artisan l5-swagger:generate
```

That is all — the image's entrypoint (`docker/entrypoint.sh`) creates `.env` if
missing, generates `APP_KEY`, generates the Passport keys, and (for the `app`
service only) runs `migrate --seed` + `ultrapos:passport-clients`, retrying
while MySQL finishes its first initialization.

Services: `nginx` (**http://localhost:8080**), `app` (php-fpm), `queue`
(worker), `scheduler` (`schedule:run` loop), `mysql` (3306), `redis`.

```bash
curl -s http://localhost:8080/api/health | jq .
```

Notes:
* `vendor/` inside the container is a **named volume** (it must survive the
  bind mount of the project). After changing `composer.json`:
  `docker compose down -v && docker compose up -d --build`.
* `composer require ...` inside the container writes into that volume, not into
  your host `vendor/`.
* On Linux the bind-mounted `storage/` and `bootstrap/cache/` must be writable
  by uid 82 (www-data): `chmod -R 775 storage bootstrap/cache`.
* Swagger UI under Docker must be pointed at the mapped port: the compose file
  sets `L5_SWAGGER_CONST_HOST=http://localhost:8080/api/v1` for you.

## 4. Authentication

Two ways to get a token; both return the same JWT-like Passport access token.

**a) First-party endpoints (recommended for POS clients)** — they register the
tenant, hash the password, create the token and return the envelope:

```bash
# 1) Register a tenant (creates business + owner + trial subscription)
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@store.test",
    "password": "SecurePass1",
    "password_confirmation": "SecurePass1",
    "business_name": "Jane'"'"'s Store",
    "business_type": "store",
    "currency": "MXN",
    "timezone": "America/Mexico_City"
  }'

# 2) Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"demo@ultrapos.test","password":"ChangeMe123!","device_name":"curl"}'

# 3) Authenticated call
TOKEN="<access_token from login>"
curl http://localhost:8000/api/v1/products \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"

# 4) Create a sale (totals are computed server-side)
curl -X POST http://localhost:8000/api/v1/sales \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "items": [{"product_id": 1, "quantity": 3}],
    "payment": {"method": "cash", "amount": 200}
  }'
```

**b) Stock OAuth2 password grant** — needs `ULTRAPOS_PASSWORD_GRANT=true`
(Passport 13 disables the grant by default; `AuthServiceProvider` re-enables it)
and the client created by `php artisan ultrapos:passport-clients`:

```bash
CLIENT_ID=... ; CLIENT_SECRET=...   # printed once by ultrapos:passport-clients
curl -sX POST http://localhost:8000/oauth/token \
  -H "Accept: application/json" \
  -d "grant_type=password&client_id=$CLIENT_ID&client_secret=$CLIENT_SECRET&scope=&username=demo@ultrapos.test&password=ChangeMe123!" | jq .
```

Tokens carry role-derived scopes (`admin`, `business`, `seller`, `customer`,
plus `sales.create`, `products.write`, … — see `AuthServiceProvider`), expire
after `PASSPORT_*_TTL` minutes, and are verified by the `auth:api` guard.

## 5. Response envelope (mandatory everywhere)

```jsonc
// Success
{
  "success": true,
  "message": "Sale created successfully",
  "data": { "id": 123, "reference_number": "SAL-20260115-00001", "total": "52.20" },
  "meta": { "timestamp": "2026-01-15T10:30:00-06:00", "request_id": "01JHZ..." }
}

// Error
{
  "success": false,
  "message": "Insufficient stock",
  "error": { "code": "BUSINESS_LOGIC_ERROR", "details": { "items": [ { "product_id": 1, "available": "2.00", "requested": 5 } ] } },
  "meta": { "timestamp": "...", "request_id": "..." }
}
```

Error codes: `VALIDATION_ERROR` (422), `UNAUTHENTICATED` (401), `UNAUTHORIZED` (403),
`NOT_FOUND` (404), `CONFLICT` (409), `BUSINESS_LOGIC_ERROR` (422), `RATE_LIMIT_EXCEEDED` (429), `SERVER_ERROR` (500).

## 6. Roles & authorization

| Role | Scope |
|------|-------|
| `admin` | Global platform administration (no tenant) |
| `business` | Tenant owner: full control of its business |
| `seller` | POS operation: sales, stock adjustments, customers, cash register |
| `customer` | Linked end-customer (read-only) |

Route middleware `role:...` (Spatie) + `EnsureBusinessScope` + Policies + tenant global scopes.

## 7. Architecture

```
app/
├── Console/Commands/        # daily reports, subscription alerts, token cleanup
├── Enums/                   # 16 backed enums (status, types, error codes)
├── Events/ Listeners/ Jobs/ # SaleCompleted → stock/receipt/low-stock (queued)
├── Exceptions/              # ApiException + typed subclasses → envelope
├── Http/
│   ├── Controllers/Api/V1/  # thin controllers (HTTP concerns only)
│   ├── Middleware/          # ForceJson, RequestId, Role, BusinessScope, Locale
│   ├── Requests/            # 47 FormRequests (validation)
│   └── Resources/           # 23 API Resources (transformation)
├── Mail/ Notifications/     # 8 mailables + queued notifications
├── Models/                  # 22 models (tenant global scopes, casts())
├── OpenApi/                 # L5-Swagger info + shared schemas
├── Observers/               # AuditObserver → audit_logs
├── Policies/                # model-level authorization
├── Services/                # business logic (SaleService, InventoryService, …)
└── Support/                 # ApiResponse trait, Money, ReferenceGenerator, RequestContext
```

Key invariants (enforced inside `DB::transaction` + `lockForUpdate`):

- Totals/taxes are **always** computed server-side; client totals are ignored.
- **Stock can never go negative** (`POS_ALLOW_NEGATIVE_STOCK=false`).
- One **open cash register per user** at a time.
- Cancelling a sale **reverts stock** and customer credit/loyalty.
- Every stock change writes an `inventory_movements` ledger row.
- Money columns are `decimal(12,2)`; timestamps ISO-8601.

## 8. Multi-tenancy

`businesses` is the tenant. `business_user` (business_id, user_id, role, joined_at) links users to tenants — `users` has **no** `business_id`. The active tenant is resolved by `EnsureBusinessScope` (route `{business}` param or the user's active business) and bound as `current_business`; `BelongsToBusiness` global scopes filter every query. Per-business `timezone`, `locale` and `currency` are applied by `SetBusinessLocale`.

## 9. Testing

```bash
php artisan test          # PHPUnit, sqlite :memory:
```

Suites: `tests/Feature` (auth, sales lifecycle, inventory, multi-tenant isolation, envelope contract) and `tests/Unit` (Money helper).

## 10. Useful commands

```bash
php artisan ultrapos:passport-clients            # provision/reuse the OAuth2 clients (idempotent)
php artisan ultrapos:send-daily-reports          # daily sales email per tenant (23:55)
php artisan ultrapos:subscription-alerts         # expire/warn subscriptions (02:00)
php artisan ultrapos:clean-expired-tokens        # purge expired/revoked OAuth tokens
php artisan l5-swagger:generate                  # regenerate the OpenAPI document
php artisan schedule:work                         # dev scheduler (runs the 3 entries above)
./vendor/bin/pint                                 # PSR-12 formatting
```

## 11. Postman

Import `postman/UltraPOS-API.postman_collection.json`. Set `base_url`, then run **Auth → Login**: the token is stored in the `token` collection variable automatically (Bearer auth is configured at collection level).

## 12. Environment

See `.env.example` for the annotated list. Notable custom variables:

| Var | Default | Purpose |
|-----|---------|---------|
| `ULTRAPOS_CURRENCY` | `USD` | Fallback currency (each business stores its own) |
| `ULTRAPOS_TRIAL_DAYS` | `14` | New tenant trial length |
| `ULTRAPOS_SUBSCRIPTION_WARNING_DAYS` | `7` | Expiring-soon notice window |
| `ULTRAPOS_STRICT_MODELS` | `false` | Eloquent strict mode (N+1, mass assignment, missing attrs) |
| `ULTRAPOS_PASSWORD_GRANT` | `true` | Expose `grant_type=password` on `POST /oauth/token` |
| `POS_LOYALTY_POINTS_PER_UNIT` | `1` | Loyalty points per currency unit paid |
| `POS_ALLOW_NEGATIVE_STOCK` | `false` | Hard invariant switch |
| `PASSPORT_ACCESS_TOKEN_TTL` | `1440` | Access token lifetime (minutes) |
| `PASSPORT_REFRESH_TOKEN_TTL` | `43200` | Refresh token lifetime (minutes) |
| `PASSPORT_PERSONAL_ACCESS_TOKEN_TTL` | `10080` | Personal access token lifetime (minutes) |
| `L5_SWAGGER_CONST_HOST` | `http://localhost:8000/api/v1` | `servers[0].url` in the OpenAPI document |
| `L5_SWAGGER_GENERATE_ALWAYS` | `true` | Rebuild the docs on every UI request (set false in prod) |

## 13. If the install misbehaves

| Symptom | Cause → fix |
|---------|-------------|
| `LogicException: Invalid key supplied` on login / any `auth:api` route | No OAuth keys → `php artisan passport:keys` (in Docker the entrypoint does it). On Windows also see the `openssl.cnf` row below. |
| `openssl_pkey_new(): Failed to locate openssl.cnf` | PHP on Windows/XAMPP has no OpenSSL config → set `OPENSSL_CONF=C:\php\extras\ssl\openssl.cnf` (or add `openssl_conf` in `php.ini`) and re-run `passport:keys`. |
| `Base table or view already exists: oauth_auth_codes` | You published Passport's migrations (`vendor:publish --tag=passport-migrations`). They are already vendored in `database/migrations/2025_01_01_000200_create_passport_tables.php` — delete the published copies. |
| `POST /oauth/token` → `{"error":"unauthorized_client"}` | Missing password-grant client or the grant is off → `php artisan ultrapos:passport-clients`, `ULTRAPOS_PASSWORD_GRANT=true`. |
| `MassAssignmentException` / `cannot set property` while seeding | Only with `ULTRAPOS_STRICT_MODELS=true`; the seeders use `forceFill()` for `email_verified_at`. Set it to `false` for a vanilla install. |
| `There are no commands defined in the "ultrapos" namespace` | Stale `bootstrap/cache/packages.php` (package discovery cached before) → `php artisan optimize:clear`. |
| `GET /api/documentation` → 404, or `Documentation config not found` | `config/l5-swagger.php` does not have the `default` / `documentations` / `defaults` shape l5-swagger 9 requires → restore the shipped file, then `php artisan l5-swagger:generate`. |
| `Connection refused` on `redis://127.0.0.1:6379` | No Redis → set `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` to `database` (tables exist) or start Redis. |
| `Specified key was too long` / `utf8mb4` errors | MySQL 5.7 or a wrong collation → use MySQL 8 with `utf8mb4`/`utf8mb4_unicode_ci` (the compose stack sets it). |
| Tests die with `Class "Laravel\Passport\PersonalAccessClient" not found` | That class was removed in Passport 13; `tests/TestCase.php` now creates the client through `ClientRepository`. |
| `php artisan test` fails on `POST /api/v1/auth/login` | Tests generate the Passport keys automatically on first run; if `storage/` is read-only, create `storage/oauth-*.key` yourself (`php artisan passport:keys`). |

---

Built API-first: no Blade views except queued email templates (`resources/views/emails`).
