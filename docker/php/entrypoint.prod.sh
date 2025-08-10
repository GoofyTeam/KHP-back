#!/usr/bin/env bash
set -Eeuo pipefail

#=== LOGGING FUNCTIONS =========================================
info()    { echo "[INFO]    $*"; }
warning() { echo "[WARNING] $*"; }
fatal()   { echo "[ERROR]   $*" >&2; exit 1; }

#=== GLOBAL VARIABLES ==========================================
WEB_ROOT="/var/www/html"
SUPERVISOR_CONF="/etc/supervisor/conf.d/supervisord.conf"
MAX_RETRIES=10
RETRY_DELAY=10

#=== FUNCTION TO CHECK IF COMMAND EXISTS =======================
command_exists() { command -v "$1" >/dev/null 2>&1; }

#=== FIX PERMISSIONS & GIT SAFE DIR ============================
info "Fixing permissions and Git safe directory..."
chown -R www-data:www-data "$WEB_ROOT"
git config --global --add safe.directory "$WEB_ROOT"

#=== INSTALL COMPOSER DEPENDENCIES =============================
if [[ -f "$WEB_ROOT/composer.json" ]]; then
  if command_exists composer; then
    info "Installing Composer dependencies..."
    cd "$WEB_ROOT" || fatal "Cannot cd to $WEB_ROOT"
    composer install --no-interaction --no-progress --optimize-autoloader --no-dev
    info "Composer dependencies installed"
  else
    warning "Composer not found, skipping install."
  fi
else
  info "composer.json not found, skipping install."
fi

#=== GENERATE APP KEY IF NOT SET ===============================
if [[ -z "${APP_KEY:-}" ]]; then
  info "APP_KEY not found in environment, generating..."
  php artisan key:generate --force
  info "Generated application key"
else
  info "APP_KEY already set, skipping generation."
fi

#=== CLEAR & CACHE CONFIG =======================================
info "Refreshing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
info "Laravel config regenerated with runtime ENV."

#=== WAIT FOR DATABASE =========================================
info "Waiting for database connection..."
DB_READY=0
for i in $(seq 1 "$MAX_RETRIES"); do
  if php artisan migrate:status >/dev/null 2>&1; then
    info "Database is reachable."
    DB_READY=1
    break
  else
    warning "Database not reachable. Retrying in $RETRY_DELAY seconds... ($i/$MAX_RETRIES)"
    sleep "$RETRY_DELAY"
  fi
done

#=== RUN MIGRATIONS ============================================
if [[ "$DB_READY" -eq 1 ]]; then
  info "Running migrations..."
  php artisan migrate --force
  info "Database migrations completed."
else
  fatal "Database connection failed after $MAX_RETRIES attempts."
fi

#=== WAIT FOR MINIO ============================================
if [[ -n "${MINIO_HOST:-}" && -n "${MINIO_PORT:-}" ]]; then
  info "Waiting for MinIO..."
  MINIO_READY=0
  for i in $(seq 1 "$MAX_RETRIES"); do
    if curl -s "http://${MINIO_HOST}:${MINIO_PORT}/minio/health/live" >/dev/null; then
      info "MinIO is ready."
      MINIO_READY=1
      break
    else
      warning "MinIO not ready. Retrying in $RETRY_DELAY seconds... ($i/$MAX_RETRIES)"
      sleep "$RETRY_DELAY"
    fi
  done

  if [[ "$MINIO_READY" -eq 1 ]]; then
    mc alias set myminio "http://${MINIO_HOST}:${MINIO_PORT}" "${MINIO_USER}" "${MINIO_PASSWORD}"
    if ! mc ls myminio | grep -q "${MINIO_BUCKET}"; then
      mc mb "myminio/${MINIO_BUCKET}"
      info "Created MinIO bucket: ${MINIO_BUCKET}"
    else
      info "MinIO bucket ${MINIO_BUCKET} already exists."
    fi
  else
    warning "MinIO is not available. Skipping bucket creation."
  fi
else
  warning "MINIO_HOST or MINIO_PORT not set. Skipping MinIO setup."
fi

#=== START SUPERVISORD =========================================
info "Starting supervisord..."
exec supervisord -c "$SUPERVISOR_CONF"
