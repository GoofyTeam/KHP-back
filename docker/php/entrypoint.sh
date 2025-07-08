#!/usr/bin/env bash

set -Eeuo pipefail

#=== LOGGING FUNCTIONS =========================================
info() {
  echo "[INFO]    $*"
}

warning() {
  echo "[WARNING] $*"
}

fatal() {
  echo "[ERROR]   $*" >&2
  exit 1
}

#=== GLOBAL VARIABLES ==========================================
WEB_ROOT="/var/www/html"
SUPERVISOR_CONF="/etc/supervisor/conf.d/supervisord.conf"
MAX_RETRIES=10
RETRY_DELAY=10

#=== FUNCTION TO CHECK IF COMMAND EXISTS =======================
command_exists() {
  command -v "$1" >/dev/null 2>&1
}

#=== START SCRIPT ==============================================
# Vérifie la présence du .env sinon copie .env.example
if [[ ! -f "$WEB_ROOT/.env" ]]; then
  if [[ -f "$WEB_ROOT/.env.example" ]]; then
    cp "$WEB_ROOT/.env.example" "$WEB_ROOT/.env"
    info ".env file was missing. Copied .env.example to .env"
  else
    fatal "Both .env and .env.example are missing. Cannot proceed."
  fi
else
  info ".env file already exists."
fi

# Installation des dépendances Composer
if [[ -f "$WEB_ROOT/composer.json" ]]; then
  if command_exists composer; then
    info "Composer file found, installing dependencies..."

    cd "$WEB_ROOT" || exit

    composer install --no-interaction --no-progress --no-suggest

    info "Composer dependencies installed"
  else
    warning "composer command not found, skipping Composer install."
  fi
else
  info "composer.json not found, skipping Composer step."
fi

# Génération de la clé APP_KEY si inexistante
if ! grep -q "APP_KEY=.\+" "$WEB_ROOT/.env"; then
  php "$WEB_ROOT/artisan" key:generate
  info "Generated application key"
fi


# Attendre la disponibilité de la base de données
info "Waiting for database connection to be ready..."
DB_READY=0
for i in $(seq 1 "$MAX_RETRIES"); do
  if php "$WEB_ROOT/artisan" db:show >/dev/null 2>&1; then
    info "Database connection successful. Running migrations..."
    DB_READY=1
    break
  else
    warning "Database connection failed. Retrying in $RETRY_DELAY seconds... (Attempt $i/$MAX_RETRIES)"
    sleep "$RETRY_DELAY"
  fi
done

# Lancer les migrations
if [[ "$DB_READY" -eq 1 ]]; then
  php "$WEB_ROOT/artisan" migrate --force
  info "Database migrations completed successfully."
else
  fatal "Database connection failed after $MAX_RETRIES attempts. Exiting..."
fi


# Initialisation de MinIO
info "Initializing MinIO..."
MINIO_HOST="${MINIO_HOST:-khp-minio}"
MINIO_PORT="${MINIO_PORT:-9000}"
MINIO_USER="${MINIO_ROOT_USER:-root}"
MINIO_PASSWORD="${MINIO_ROOT_PASSWORD:-password}"
MINIO_BUCKET="developp"

# Attendre que MinIO soit disponible
info "Waiting for MinIO to be ready..."
MINIO_READY=0
for i in $(seq 1 "$MAX_RETRIES"); do
  if curl -s "http://${MINIO_HOST}:${MINIO_PORT}/minio/health/live" > /dev/null; then
    info "MinIO is ready."
    MINIO_READY=1
    break
  else
    warning "MinIO not ready. Retrying in $RETRY_DELAY seconds... (Attempt $i/$MAX_RETRIES)"
    sleep "$RETRY_DELAY"
  fi
done

if [[ "$MINIO_READY" -eq 1 ]]; then
  # Configurer le client mc
  mc alias set myminio http://${MINIO_HOST}:${MINIO_PORT} "${MINIO_USER}" "${MINIO_PASSWORD}"

  # Créer le bucket s'il n'existe pas
  if ! mc ls myminio | grep -q "${MINIO_BUCKET}"; then
    mc mb myminio/${MINIO_BUCKET}
    info "Created MinIO bucket: ${MINIO_BUCKET}"
  else
    info "MinIO bucket ${MINIO_BUCKET} already exists."
  fi
else
  warning "MinIO is not available. Skipping bucket creation."
fi


php "$WEB_ROOT/artisan" lighthouse:ide-helper

supervisord -c "$SUPERVISOR_CONF"
