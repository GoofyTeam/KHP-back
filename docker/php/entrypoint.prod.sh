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
  # On NE génère PAS de clé ici pour éviter toute dépendance à .env
fi

info "Clearing Laravel caches early..."
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true
php artisan cache:clear  || true

#=== DEBUG RAPIDE DB (optionnel, utile en cours de mise au point) ===
info "DB target → ${DB_CONNECTION:-}(host=${DB_HOST:-}:${DB_PORT:-}, db=${DB_DATABASE:-}, user=${DB_USERNAME:-})"

#=== WAIT FOR DATABASE =========================================
info "Waiting for database connection..."
DB_READY=0
for i in $(seq 1 "$MAX_RETRIES"); do
  info "Effective Laravel DB host: $(php -r 'echo config("database.connections.pgsql.host");')"
  if php artisan migrate:status >/dev/null 2>&1; then
    info "Database is reachable."
    DB_READY=1
    break
  else
    warning "Database not reachable. Retrying in ${RETRY_DELAY}s... (${i}/${MAX_RETRIES})"
    echo "[DEBUG] Shell DB_HOST=${DB_HOST} DB_DATABASE=${DB_DATABASE} DB_USERNAME=${DB_USERNAME}"
    sleep "$RETRY_DELAY"
  fi
done

#=== RUN MIGRATIONS ============================================
if [[ "$DB_READY" -eq 1 ]]; then
  info "Running migrations..."
  php artisan migrate --force
  info "Migrations completed."
else
  fatal "Database connection failed after $MAX_RETRIES attempts."
fi

#=== CACHES LARAVEL ============================================
info "Refreshing Laravel caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
info "Laravel caches are ready."

#=== MINIO (facultatif) ========================================
if [[ -n "${MINIO_HOST:-}" && -n "${MINIO_PORT:-}" ]]; then
  info "Waiting for MinIO..."
  MINIO_READY=0
  for i in $(seq 1 "$MAX_RETRIES"); do
    if curl -s "http://${MINIO_HOST}:${MINIO_PORT}/minio/health/live" >/dev/null; then
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
