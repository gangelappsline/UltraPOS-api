#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# UltraPOS API container bootstrap.
#
# docker-compose bind-mounts the project over /var/www/html, so every artefact
# that a fresh Laravel install needs (.env, APP_KEY, Passport RSA keys, the
# schema) has to be created at runtime, not at image build time.
#
# Everything is best-effort and non-fatal: a warning is printed and the
# requested command (php-fpm / queue:work / schedule loop) still starts, so you
# can always `docker compose exec app php artisan <command>` to fix it.
# ─────────────────────────────────────────────────────────────────────────────
set -uo pipefail

APP_DIR=/var/www/html
cd "$APP_DIR" || exit 1

warn() { printf '  ! %s\n' "$1" >&2; }
info() { printf '  > %s\n' "$1" >&2; }

# ── .env ────────────────────────────────────────────────────────────────────
if [ ! -f .env ]; then
    if cp .env.example .env 2>/dev/null; then
        info "created .env from .env.example"
    else
        warn "no .env and .env.example is not writable by uid $(id -u)."
        warn "run:  cp .env.example .env   on the host, then:  docker compose up -d"
    fi
fi

# ── APP_KEY ─────────────────────────────────────────────────────────────────
if [ -f .env ] && ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
    if php artisan key:generate --force >/dev/null 2>&1; then
        info "generated APP_KEY"
    else
        warn "php artisan key:generate failed (is .env writable?) — run it manually"
    fi
fi

# ── Passport RSA keys (needed to sign/verify any access token) ───────────────
# Only one service should create them (the others would race on the same file).
if [ "${ULTRAPOS_AUTO_KEYS:-true}" = "true" ] && [ ! -f storage/oauth-private.key ]; then
    if php artisan passport:keys >/dev/null 2>&1; then
        info "generated Passport OAuth keys in storage/"
    else
        warn "php artisan passport:keys failed (is storage/ writable?) — run it manually"
    fi
elif [ ! -f storage/oauth-private.key ]; then
    info "waiting for the app container to create the Passport keys"
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        [ -f storage/oauth-private.key ] && break
        sleep 2
    done
fi

# ── Migrations (only for the service that opts in) ───────────────────────────
if [ "${ULTRAPOS_AUTO_MIGRATE:-false}" = "true" ]; then
    # MySQL's healthcheck passes before the initial data directory is ready,
    # so retry instead of failing the whole stack.
    attempt=0
    until php artisan migrate --force ${ULTRAPOS_AUTO_SEED:+--seed} >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge 12 ]; then
            warn "migrate still failing after ${attempt} attempts; run it manually:"
            warn "  docker compose exec app php artisan migrate --seed"
            break
        fi
        info "waiting for the database (attempt ${attempt})…"
        sleep 5
    done
    [ "$attempt" -eq 0 ] && info "database schema up to date"
fi

exec "$@"
