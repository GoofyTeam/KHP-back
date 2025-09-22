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
artisan() { php artisan "$@" --no-ansi --no-interaction; }

#=== PREP (perms + git safe dir) ===============================
info "Fixing permissions and Git safe directory..."
chown -R www-data:www-data "$WEB_ROOT" || true
git config --global --add safe.directory "$WEB_ROOT" || true
export COMPOSER_ALLOW_SUPERUSER=1

#=== COMPOSER ==================================================
if [[ -f "$WEB_ROOT/composer.json" ]] && command_exists composer; then
  info "Installing Composer dependencies..."
  cd "$WEB_ROOT"
  if [[ "${APP_ENV:-production}" == "local" ]]; then
    composer install --no-interaction --no-progress --optimize-autoloader
  else
    composer install --no-interaction --no-progress --optimize-autoloader --no-dev
  fi
  info "Composer dependencies installed"
else
  info "Composer not found or composer.json missing, skipping."
fi

#=== APP_KEY (NE PAS GENERER EN PROD) ==========================
if [[ -z "${APP_KEY:-}" ]]; then
  warning "APP_KEY is NOT set in environment! Set it via Kubernetes Secret and redeploy."
fi

#=== CLEAR CACHES EARLY ========================================
info "Clearing Laravel caches early (config/route/view only)..."
artisan config:clear || true
artisan route:clear  || true
artisan view:clear   || true

# Si le store est Redis ou file, on peut clear le cache applicatif aussi.
CACHE_STORE_SAFE="${CACHE_STORE:-file}"
if [[ "$CACHE_STORE_SAFE" != "database" ]]; then
  info "Early cache:clear (store=$CACHE_STORE_SAFE)"
  artisan cache:clear || true
else
  info "Skipping early cache:clear because CACHE_STORE=database"
fi

#=== DEBUG DB (env + réel Laravel) =============================
info "DB target (env) → ${DB_CONNECTION:-}(host=${DB_HOST:-}:${DB_PORT:-}, db=${DB_DATABASE:-}, user=${DB_USERNAME:-})"
EFFECTIVE_DB_HOST="$(php artisan tinker --execute='echo config("database.connections.pgsql.host");' --no-ansi 2>/dev/null || true)"
info "Effective Laravel DB host: ${EFFECTIVE_DB_HOST:-<unknown>}"

#=== WAIT FOR DATABASE (PDO only) ==============================
info "Quick PDO check (raw, before Laravel)..."
DB_READY=0
for i in $(seq 1 "$MAX_RETRIES"); do
  if php -r '
    $h=getenv("DB_HOST"); $p=getenv("DB_PORT")?:5432; $d=getenv("DB_DATABASE");
    $u=getenv("DB_USERNAME"); $w=getenv("DB_PASSWORD");
    $dsn="pgsql:host=$h;port=$p;dbname=$d";
    try { new PDO($dsn,$u,$w,[PDO::ATTR_TIMEOUT=>3]); exit(0); }
    catch(Exception $e){ fwrite(STDERR,$e->getMessage().PHP_EOL); exit(2); }
  '; then
    info "Database is reachable (PDO_OK)."
    DB_READY=1
    break
  else
    warning "Database not reachable. Retrying in ${RETRY_DELAY}s... (${i}/${MAX_RETRIES})"
    echo "[DEBUG] Shell DB_HOST=${DB_HOST:-} DB_DATABASE=${DB_DATABASE:-} DB_USERNAME=${DB_USERNAME:-}"
    sleep "$RETRY_DELAY"
  fi
done

if [[ "$DB_READY" -ne 1 ]]; then
  fatal "Database connection failed after $MAX_RETRIES attempts."
fi

#=== RUN MIGRATIONS (direct, sans pre-check) ===================
info "Running migrations..."
# if ! OUTPUT=$(artisan migrate --force -vvv 2>&1); then
#   echo "$OUTPUT"
#   fatal "Migrations failed."
# fi
#Temporary create fake data for development purposes (remove in production) (change app_env in secrets)

php artisan migrate:fresh --seed
php artisan db:seed --class=LyonnaiseCompanySeeder --no-interaction --no-ansi

info "Migrations completed."

#=== CACHE STORE=database : maintenant possible =================
if [[ "$CACHE_STORE_SAFE" == "database" ]]; then
  # Assure-toi d'avoir la migration 'cache' committée si tu utilises ce store.
  info "Clearing cache on database store (post-migrate)..."
  artisan cache:clear || true
fi

#=== REBUILD CACHES ============================================
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

#Temporary create fake data for development purposes (remove in production) (change app_env in secrets)
php artisan migrate:fresh --seed
php artisan db:seed --class=LyonnaiseCompanySeeder --no-interaction --no-ansi

#=== START SUPERVISORD =========================================
info "Starting supervisord..."
exec supervisord -c "$SUPERVISOR_CONF"
