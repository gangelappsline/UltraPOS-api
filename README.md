# UltraPOS API

Multi-tenant Point-of-Sale REST API built with **Laravel 12**, **PHP 8.3+**, **Laravel Passport** (OAuth2), **MySQL 8**, **Redis** and **L5-Swagger** (OpenAPI 3).

Every tenant is a `Business`. Tenancy is enforced by the `business_user` pivot plus global model scopes, so a user can **never** read or write another tenant's data.

- **API base:** `/api/v1`
- **Swagger UI:** `/api/documentation`
- **OpenAPI JSON:** `/api/documentation.json`
- **Health check:** `GET /api/health`

---

## 1. Requirements

| Tool | Version |
|------|---------|
| PHP | 8.3+ (`bcmath`, `intl`, `pdo_mysql`, `gd`, `zip`, `opcache`, `redis` ext) |
| Composer | 2.x |
| MySQL | 8.x |
| Redis | 6+ (queues, cache, rate limiting) |
| Node | not required — this project is API-only |

## 2. Quick start

```bash
git clone <repo-url> ultrapos-api && cd ultrapos-api
composer install
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed        # roles, permissions, plans, admin, demo tenant

# OAuth2 (Passport)
php artisan passport:install      # encryption keys + clients

# OpenAPI docs
php artisan l5-swagger:generate   # serves /api/documentation

# Serve
php artisan serve                 # http://localhost:8000

# Queued mails / notifications (second terminal)
php artisan queue:work redis --tries=3
```

Default seeded accounts (change them!):

| Role | Email | Password |
|------|-------|----------|
| Global admin | `admin@ultrapos.test` | `ChangeMe123!` |
| Business owner (demo) | `demo@ultrapos.test` | `ChangeMe123!` |
| Seller (demo) | `seller@ultrapos.test` | `ChangeMe123!` |

## 3. Docker

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan passport:install
docker compose exec app php artisan l5-swagger:generate
```

Services: `nginx` (port **8080**), `app` (php-fpm), `queue` (worker), `scheduler` (cron), `mysql` (3306), `redis`.

## 4. Authentication

Passport **password grant** + **personal access tokens**. Tokens carry role-derived scopes
(`admin`, `business.manage`, `sales.create`, …). Send the token as a Bearer header:

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
php artisan ultrapos:send-daily-reports       # daily sales email per tenant
php artisan ultrapos:subscription-alerts      # expiring subscription notices
php artisan ultrapos:clean-expired-tokens     # revoke expired OAuth tokens
php artisan l5-swagger:generate               # regenerate OpenAPI docs
./vendor/bin/pint                             # PSR-12 formatting
```

## 11. Postman

Import `postman/UltraPOS-API.postman_collection.json`. Set `base_url`, then run **Auth → Login**: the token is stored in the `token` collection variable automatically (Bearer auth is configured at collection level).

## 12. Environment

See `.env.example`. Notable custom variables:

| Var | Default | Purpose |
|-----|---------|---------|
| `POS_CURRENCY` | `USD` | Default tenant currency |
| `POS_LOYALTY_POINTS_PER_UNIT` | `1` | Loyalty points per currency unit paid |
| `POS_ALLOW_NEGATIVE_STOCK` | `false` | Hard invariant switch |
| `POS_TRIAL_DAYS` | `14` | New tenant trial length |
| `POS_SUBSCRIPTION_WARNING_DAYS` | `7` | Expiring-soon notice window |

---

Built API-first: no Blade views except queued email templates (`resources/views/emails`).
