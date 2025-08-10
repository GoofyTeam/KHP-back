#!/usr/bin/env bash
set -Eeuo pipefail

#=== LOGGING ===================================================
info()    { echo "[INFO]    $*"; }
warning() { echo "[WARNING] $*"; }
fatal()   { echo "[ERROR]   $*" >&2; exit 1; }

#=== GLOBALS ===================================================
WEB_ROOT="/var/www/html"
SUPERVISOR_CONF="/etc/supervisor/conf.d/supervisord.conf"
MAX_RETRIES=20
RETRY_DELAY=10

command_exists() { command -v "$1" >/dev/null 2>&1; }

artisan() {
  # Centralise les flags pour avoir des logs propres en CI
  php artisan "$@" --no-ansi --no-interaction
}

#=== PREP (perms + git safe dir) ===============================
info "Fixing permissions and Git safe directory..."
chown -R www-data:www-data "$WEB_ROOT" || true
git config --global --add safe.directory "$WEB_ROOT" || true
export COMPOSER_ALLOW_SUPERUSER=1

#=== COMPOSER ==================================================
if [[ -f "$WEB_ROOT/composer.json" ]] && command_exists composer; then
  info "Installing Composer dependencies..."
  cd "$WEB_ROOT"
  composer install --no-interaction --no-progress --optimize-autoloader --no-dev
  info "Composer dependencies installed"
else
  info "Composer not found or composer.json missing, skipping."
fi

#=== APP_KEY (NE PAS GENERER EN PROD) ==========================
if [[ -z "${APP_KEY:-}" ]]; then
  warning "APP_KEY is NOT set in environment! Set it via Kubernetes Secret and redeploy."
fi

#=== CLEAR CACHES TÔT (sans toucher au cache store) ============
info "Clearing Laravel caches early (config/route/view only)..."
artisan config:clear || true
artisan route:clear  || true
artisan view:clear   || true

# IMPORTANT :
# On NE fait PAS 'cache:clear' ici si CACHE_STORE=database, sinon Laravel essaie de DELETE FROM cache (table inexistante).
CACHE_STORE_SAFE="${CACHE_STORE:-file}"
if [[ "$CACHE_STORE_SAFE" != "database" ]]; then
  info "Early cache:clear (store=$CACHE_STORE_SAFE)"
  artisan cache:clear || true
else
  info "Skipping early cache:clear because CACHE_STORE=database"
fi

#=== DEBUG RAPIDE DB (environnement + réel Laravel) ============
info "DB target (env) → ${DB_CONNECTION:-}(host=${DB_HOST:-}:${DB_PORT:-}, db=${DB_DATABASE:-}, user=${DB_USERNAME:-})"
# Lire la config réellement chargée par Laravel (via bootstrap), silencieusement si tinker n'est pas installé :
EFFECTIVE_DB_HOST="$(php artisan tinker --execute='echo config("database.connections.pgsql.host");' --no-ansi 2>/dev/null || true)"
info "Effective Laravel DB host: ${EFFECTIVE_DB_HOST:-<unknown>}"

#=== WAIT FOR DATABASE =========================================
info "Waiting for database connection..."
DB_READY=0
for i in $(seq 1 "$MAX_RETRIES"); do
  if artisan migrate:status >/dev/null 2>&1; then
    info "Database is reachable."
    DB_READY=1
    break
  else
    warning "Database not reachable. Retrying in ${RETRY_DELAY}s... (${i}/${MAX_RETRIES})"
    echo "[DEBUG] Shell DB_HOST=${DB_HOST:-} DB_DATABASE=${DB_DATABASE:-} DB_USERNAME=${DB_USERNAME:-}"
    sleep "$RETRY_DELAY"
  fi
done

#=== RUN MIGRATIONS ============================================
if [[ "$DB_READY" -eq 1 ]]; then
  info "Running migrations..."
  artisan migrate --force
  info "Migrations completed."
else
  fatal "Database connection failed after $MAX_RETRIES attempts."
fi

#=== CACHE STORE=database : maintenant on peut vider ============
if [[ "$CACHE_STORE_SAFE" == "database" ]]; then
  # À ce stade, si tu utilises vraiment le cache 'database', assure-toi d'avoir la migration de la table 'cache' dans ton code.
  # (tu peux la générer une fois: `php artisan cache:table` puis commit)
  info "Now clearing cache on database store..."
  artisan cache:clear || true
fi

#=== REBUILD CACHES (après migrations & env OK) =================
info "Rebuilding Laravel caches..."
artisan config:cache || true
artisan route:cache  || true
artisan view:cache   || true
info "Laravel caches are ready."

#=== MINIO (facultatif) ========================================
if [[ -n "${MINIO_HOST:-}" && -n "${MINIO_PORT:-}" ]]; then
  info "Waiting for MinIO..."
  MINIO_READY=0
  for i in $(seq 1 "$MAX_RETRIES"); do
    if curl -fsS "http://${MINIO_HOST}:${MINIO_PORT}/minio/health/live" >/dev/null; then
      info "MinIO is ready."
      MINIO_READY=1
      break
    else
      warning "MinIO not ready. Retrying in ${RETRY_DELAY}s... (${i}/${MAX_RETRIES})"
      sleep "$RETRY_DELAY"
    fi
  done

  if [[ "$MINIO_READY" -eq 1 && -n "${MINIO_USER:-}" && -n "${MINIO_PASSWORD:-}" && -n "${MINIO_BUCKET:-}" ]]; then
    mc alias set myminio "http://${MINIO_HOST}:${MINIO_PORT}" "${MINIO_USER}" "${MINIO_PASSWORD}" || true
    if ! mc ls myminio | grep -q "${MINIO_BUCKET}"; then
      mc mb "myminio/${MINIO_BUCKET}" || true
      info "Ensured MinIO bucket: ${MINIO_BUCKET}"
    else
      info "MinIO bucket ${MINIO_BUCKET} already exists."
    fi
  else
    warning "MinIO not ready or creds/bucket not set — skipping bucket ensure."
  fi
else
  warning "MINIO_HOST or MINIO_PORT not set — skipping MinIO checks."
fi

#=== START SUPERVISORD =========================================
info "Starting supervisord..."
exec supervisord -c "$SUPERVISOR_CONF"
