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

#=== ALIGN WWW-DATA USER WITH HOST =============================
sync_www_data_identity() {
  local current_uid current_gid target_uid target_gid group_name existing_user

  current_uid=$(id -u www-data)
  current_gid=$(id -g www-data)

  target_uid=${WWWUSER:-$(stat -c '%u' "$WEB_ROOT" 2>/dev/null || echo "$current_uid")}
  target_gid=${WWWGROUP:-$(stat -c '%g' "$WEB_ROOT" 2>/dev/null || echo "$current_gid")}

  if [[ "$target_gid" != "$current_gid" ]]; then
    local group_entry
    group_entry=$(get_group_entry_by_gid "$target_gid")
    if [[ -n "$group_entry" ]]; then
      group_name=$(echo "$group_entry" | cut -d: -f1)
      info "Assigning www-data to existing group ${group_name} (gid: ${target_gid})"
      usermod -g "$group_name" www-data
    else
      info "Updating www-data primary group id from ${current_gid} to ${target_gid}"
      groupmod -g "$target_gid" www-data
    fi
  fi

  if [[ "$target_uid" != "$current_uid" ]]; then
    local passwd_entry
    passwd_entry=$(get_passwd_entry_by_uid "$target_uid")
    if [[ -n "$passwd_entry" && "$(echo "$passwd_entry" | cut -d: -f1)" != "www-data" ]]; then
      existing_user=$(echo "$passwd_entry" | cut -d: -f1)
      warning "UID ${target_uid} already used by ${existing_user}. Skipping www-data UID reassignment."
    else
      info "Updating www-data user id from ${current_uid} to ${target_uid}"
      usermod -u "$target_uid" www-data
    fi
  fi
}

#=== FUNCTION TO CHECK IF COMMAND EXISTS =======================
command_exists() {
  command -v "$1" >/dev/null 2>&1
}

get_group_entry_by_gid() {
  local gid=$1
  if command_exists getent; then
    getent group "$gid" || true
  else
    grep -E "^[^:]+:[^:]*:${gid}:" /etc/group || true
  fi
}

get_passwd_entry_by_uid() {
  local uid=$1
  if command_exists getent; then
    getent passwd "$uid" || true
  else
    grep -E "^[^:]+:[^:]*:${uid}:" /etc/passwd || true
  fi
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

# Aligne l'utilisateur www-data sur les UID/GID du volume hôte
info "Synchronizing www-data user and group IDs with mounted volume..."
sync_www_data_identity

# S'assure que les dossiers de cache sont présents et accessibles
info "Ensuring writable permissions for storage and cache directories..."
mkdir -p \
  "$WEB_ROOT/storage/logs" \
  "$WEB_ROOT/storage/framework/cache" \
  "$WEB_ROOT/storage/framework/sessions" \
  "$WEB_ROOT/storage/framework/views"
touch "$WEB_ROOT/storage/logs/laravel.log"
WWW_DATA_GROUP=$(id -gn www-data)
chown -R www-data:"$WWW_DATA_GROUP" "$WEB_ROOT/storage" "$WEB_ROOT/bootstrap/cache"
chmod -R ug+rwx "$WEB_ROOT/storage" "$WEB_ROOT/bootstrap/cache"
chmod ug+rw "$WEB_ROOT/storage/logs/laravel.log"

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
