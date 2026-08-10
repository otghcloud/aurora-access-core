#!/usr/bin/env bash
set -euo pipefail

TARGET_DIR=""
CORE_VERSION="^1.0"
WITH_ADAPTERS=false
NON_INTERACTIVE=false
LOCAL_PACKAGES_DIR=""
VALIDATE_ONLY=false
PROMPT_FD=0

usage() {
  cat <<'EOF'
Aurora Access Core setup

Usage:
  bash setup.sh [options]

Options:
  --target <directory>          Target directory for new Laravel app
  --core-version <constraint>   Version constraint for aurora-access-core (default: ^1.0)
  --with-adapters               Install Aurora adapter packages
  --use-local-packages <dir>    Use local package paths from <dir>/packages/*
  --validate-only               Check prerequisites and exit
  --non-interactive             Use DEFAULT_* environment values and skip prompts
  -h, --help                    Show this help

Examples:
  bash setup.sh
  bash setup.sh --target ./aurora-access
  DEFAULT_DB_CONNECTION=sqlite DEFAULT_DB_DATABASE=database/database.sqlite \
  DEFAULT_ADMIN_NAME="Admin" DEFAULT_ADMIN_EMAIL="admin@example.com" \
  DEFAULT_ADMIN_PASSWORD="changeme123" bash setup.sh --non-interactive
EOF
}

log_step() {
  echo "==> $*"
}

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

require_cmd() {
  local cmd="$1"
  if ! command -v "$cmd" >/dev/null 2>&1; then
    fail "Missing required command: $cmd"
  fi
}

require_php_version() {
  if ! php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    fail "PHP 8.3+ is required"
  fi
}

require_php_extensions() {
  local missing=()
  local ext
  for ext in ctype fileinfo json mbstring openssl pdo tokenizer xml; do
    if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
      missing+=("$ext")
    fi
  done

  if [[ ${#missing[@]} -gt 0 ]]; then
    fail "Missing required PHP extensions: ${missing[*]}"
  fi
}

validate_email() {
  local value="$1"
  if ! php -r 'exit(filter_var($argv[1], FILTER_VALIDATE_EMAIL) ? 0 : 1);' "$value"; then
    fail "Invalid email address: $value"
  fi
}

init_prompt_input() {
  if [[ "$NON_INTERACTIVE" == "true" ]]; then
    return
  fi

  if [[ -t 0 ]]; then
    PROMPT_FD=0
    return
  fi

  if [[ -r /dev/tty ]]; then
    exec 3</dev/tty
    PROMPT_FD=3
    return
  fi

  fail "Interactive prompts require a TTY. Use --non-interactive with DEFAULT_* values."
}

prompt_value() {
  local label="$1"
  local default_value="$2"
  local secret="${3:-false}"

  if [[ "$NON_INTERACTIVE" == "true" ]]; then
    echo "$default_value"
    return
  fi

  local entered
  if [[ "$secret" == "true" ]]; then
    read -r -u "$PROMPT_FD" -s -p "$label: " entered
    echo
  else
    if [[ -n "$default_value" ]]; then
      read -r -u "$PROMPT_FD" -p "$label [$default_value]: " entered
    else
      read -r -u "$PROMPT_FD" -p "$label: " entered
    fi
  fi

  if [[ -z "$entered" ]]; then
    echo "$default_value"
  else
    echo "$entered"
  fi
}

prompt_required_value() {
  local label="$1"
  local default_value="$2"
  local secret="${3:-false}"

  while true; do
    local value
    value="$(prompt_value "$label" "$default_value" "$secret")"
    if [[ -n "$value" ]]; then
      echo "$value"
      return
    fi

    if [[ "$NON_INTERACTIVE" == "true" ]]; then
      fail "$label is required in non-interactive mode"
    fi

    echo "$label cannot be empty"
  done
}

prompt_db_connection() {
  if [[ "$NON_INTERACTIVE" == "true" ]]; then
    local value="${DEFAULT_DB_CONNECTION:-}"
    if [[ -z "$value" ]]; then
      fail "DEFAULT_DB_CONNECTION is required in non-interactive mode"
    fi
    echo "$value"
    return
  fi

  while true; do
    local value
    value="$(prompt_required_value "DB_CONNECTION (sqlite|mariadb|mysql|pgsql|sqlsrv)" "")"
    case "$value" in
      sqlite|mariadb|mysql|pgsql|sqlsrv)
        echo "$value"
        return
        ;;
      *)
        echo "Please choose one of: sqlite, mariadb, mysql, pgsql, sqlsrv"
        ;;
    esac
  done
}

prompt_yes_no() {
  local label="$1"
  local default_answer="$2"

  if [[ "$NON_INTERACTIVE" == "true" ]]; then
    echo "$default_answer"
    return
  fi

  while true; do
    local suffix="[y/N]"
    if [[ "$default_answer" == "yes" ]]; then
      suffix="[Y/n]"
    fi

    local answer
    read -r -u "$PROMPT_FD" -p "$label $suffix: " answer

    if [[ -z "$answer" ]]; then
      echo "$default_answer"
      return
    fi

    case "$answer" in
      y|Y|yes|YES)
        echo "yes"
        return
        ;;
      n|N|no|NO)
        echo "no"
        return
        ;;
      *)
        echo "Please answer yes or no"
        ;;
    esac
  done
}

set_env_value() {
  local env_file="$1"
  local key="$2"
  local value="$3"

  if [[ ! -f "$env_file" ]]; then
    fail "Environment file not found: $env_file"
  fi

  php -r '
    $file = $argv[1];
    $key = $argv[2];
    $value = $argv[3];

    $contents = file_exists($file) ? file_get_contents($file) : "";
    $lines = $contents === "" ? [] : preg_split("/\r\n|\n|\r/", $contents);
    $updated = false;

    foreach ($lines as $index => $line) {
      if (str_starts_with($line, $key . "=")) {
        $lines[$index] = $key . "=" . $value;
        $updated = true;
      }
    }

    if (!$updated) {
      $lines[] = $key . "=" . $value;
    }

    file_put_contents($file, rtrim(implode(PHP_EOL, $lines), PHP_EOL) . PHP_EOL);
  ' "$env_file" "$key" "$value"
}

quote_env_value() {
  local value="$1"
  value="${value//$'\r'/}"
  value="${value//$'\n'/}"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

nullable_env_value() {
  local value="$1"
  if [[ -z "$value" ]]; then
    printf '""'
    return
  fi

  if [[ "$value" =~ ^[Nn][Uu][Ll][Ll]$ ]]; then
    printf 'null'
    return
  fi

  quote_env_value "$value"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --target)
      TARGET_DIR="${2:-}"
      shift 2
      ;;
    --core-version)
      CORE_VERSION="${2:-}"
      shift 2
      ;;
    --with-adapters)
      WITH_ADAPTERS=true
      shift
      ;;
    --use-local-packages)
      LOCAL_PACKAGES_DIR="${2:-}"
      shift 2
      ;;
    --non-interactive)
      NON_INTERACTIVE=true
      shift
      ;;
    --validate-only)
      VALIDATE_ONLY=true
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      fail "Unknown option: $1"
      ;;
  esac
done

init_prompt_input

if [[ -z "$TARGET_DIR" ]]; then
  target_default="${DEFAULT_TARGET_DIR:-./aurora-access}"
  TARGET_DIR="$(prompt_required_value "Installation directory" "$target_default")"
fi

if [[ "$WITH_ADAPTERS" == "false" ]]; then
  adapters_answer="$(prompt_yes_no "Install optional Aurora adapters?" "no")"
  if [[ "$adapters_answer" == "yes" ]]; then
    WITH_ADAPTERS=true
  fi
fi

if [[ -e "$TARGET_DIR" && -n "$(ls -A "$TARGET_DIR" 2>/dev/null || true)" ]]; then
  fail "Target directory already exists and is not empty: $TARGET_DIR"
fi

log_step "Checking prerequisites"
require_cmd php
require_cmd composer
require_cmd npm
require_php_version
require_php_extensions

if [[ "$VALIDATE_ONLY" == "true" ]]; then
  if [[ -n "$LOCAL_PACKAGES_DIR" ]]; then
    local_root="$(cd "$LOCAL_PACKAGES_DIR" && pwd)"
    if [[ ! -d "$local_root/packages/access-core" ]]; then
      fail "Local package path not found: $local_root/packages/access-core"
    fi
  fi

  log_step "Prerequisite validation passed"
  exit 0
fi

APP_NAME_DEFAULT="${DEFAULT_APP_NAME:-Aurora Access Control}"
APP_URL_DEFAULT="${DEFAULT_APP_URL:-http://localhost}"

app_name="$(prompt_required_value "APP_NAME" "$APP_NAME_DEFAULT")"
app_url="$(prompt_required_value "APP_URL" "$APP_URL_DEFAULT")"

db_connection="$(prompt_db_connection)"
case "$db_connection" in
  sqlite)
    db_name="$(prompt_required_value "DB_DATABASE" "${DEFAULT_DB_DATABASE:-database/database.sqlite}")"
    db_host=""
    db_port=""
    db_username=""
    db_password=""
    ;;
  mariadb|mysql)
    db_host="$(prompt_required_value "DB_HOST" "${DEFAULT_DB_HOST:-127.0.0.1}")"
    db_port="$(prompt_required_value "DB_PORT" "${DEFAULT_DB_PORT:-3306}")"
    db_name="$(prompt_required_value "DB_DATABASE" "${DEFAULT_DB_DATABASE:-access_controller}")"
    db_username="$(prompt_required_value "DB_USERNAME" "${DEFAULT_DB_USERNAME:-root}")"
    db_password="$(prompt_value "DB_PASSWORD" "${DEFAULT_DB_PASSWORD:-}" "true")"
    ;;
  pgsql)
    db_host="$(prompt_required_value "DB_HOST" "${DEFAULT_DB_HOST:-127.0.0.1}")"
    db_port="$(prompt_required_value "DB_PORT" "${DEFAULT_DB_PORT:-5432}")"
    db_name="$(prompt_required_value "DB_DATABASE" "${DEFAULT_DB_DATABASE:-access_controller}")"
    db_username="$(prompt_required_value "DB_USERNAME" "${DEFAULT_DB_USERNAME:-postgres}")"
    db_password="$(prompt_value "DB_PASSWORD" "${DEFAULT_DB_PASSWORD:-}" "true")"
    ;;
  sqlsrv)
    db_host="$(prompt_required_value "DB_HOST" "${DEFAULT_DB_HOST:-127.0.0.1}")"
    db_port="$(prompt_required_value "DB_PORT" "${DEFAULT_DB_PORT:-1433}")"
    db_name="$(prompt_required_value "DB_DATABASE" "${DEFAULT_DB_DATABASE:-access_controller}")"
    db_username="$(prompt_required_value "DB_USERNAME" "${DEFAULT_DB_USERNAME:-sa}")"
    db_password="$(prompt_value "DB_PASSWORD" "${DEFAULT_DB_PASSWORD:-}" "true")"
    ;;
  *)
    fail "Unsupported DB connection: $db_connection"
    ;;
esac

redis_host="$(prompt_required_value "REDIS_HOST" "${DEFAULT_REDIS_HOST:-127.0.0.1}")"
redis_port="$(prompt_required_value "REDIS_PORT" "${DEFAULT_REDIS_PORT:-6379}")"
redis_password="$(prompt_value "REDIS_PASSWORD" "${DEFAULT_REDIS_PASSWORD:-null}" "true")"

mqtt_host="$(prompt_required_value "MQTT_HOST" "${DEFAULT_MQTT_HOST:-127.0.0.1}")"
mqtt_port="$(prompt_required_value "MQTT_PORT" "${DEFAULT_MQTT_PORT:-1883}")"
mqtt_client_id="$(prompt_required_value "MQTT_CLIENT_ID" "${DEFAULT_MQTT_CLIENT_ID:-ag-access}")"
mqtt_monitor_connection="$(prompt_required_value "MQTT_MONITOR_CONNECTION" "${DEFAULT_MQTT_MONITOR_CONNECTION:-monitor}")"
mqtt_monitor_client_id="$(prompt_required_value "MQTT_MONITOR_CLIENT_ID" "${DEFAULT_MQTT_MONITOR_CLIENT_ID:-${mqtt_client_id}-monitor}")"
mqtt_username="$(prompt_value "MQTT_AUTH_USERNAME" "${DEFAULT_MQTT_AUTH_USERNAME:-}")"
mqtt_password="$(prompt_value "MQTT_AUTH_PASSWORD" "${DEFAULT_MQTT_AUTH_PASSWORD:-}" "true")"

admin_name="$(prompt_required_value "INITIAL_ADMIN_NAME" "${DEFAULT_ADMIN_NAME:-Admin User}")"
admin_email="$(prompt_required_value "INITIAL_ADMIN_EMAIL" "${DEFAULT_ADMIN_EMAIL:-admin@example.com}")"
validate_email "$admin_email"

if [[ "$NON_INTERACTIVE" == "true" ]]; then
  admin_password="${DEFAULT_ADMIN_PASSWORD:-}"
  if [[ -z "$admin_password" ]]; then
    fail "DEFAULT_ADMIN_PASSWORD is required in non-interactive mode"
  fi
else
  while true; do
    admin_password="$(prompt_required_value "INITIAL_ADMIN_PASSWORD" "" "true")"
    admin_password_confirm="$(prompt_required_value "INITIAL_ADMIN_PASSWORD_CONFIRM" "" "true")"

    if [[ "$admin_password" != "$admin_password_confirm" ]]; then
      echo "Admin passwords do not match"
      continue
    fi

    break
  done
fi

if [[ "${#admin_password}" -lt 8 ]]; then
  fail "Admin password must be at least 8 characters"
fi

log_step "Creating Laravel host app in $TARGET_DIR"
composer create-project laravel/laravel "$TARGET_DIR"

cd "$TARGET_DIR"
TARGET_DIR="$(pwd)"

adapter_package_constraint="^1.0"
core_package_constraint="$CORE_VERSION"

if [[ -n "$LOCAL_PACKAGES_DIR" ]]; then
  local_root="$(cd "$LOCAL_PACKAGES_DIR" && pwd)"

  if [[ ! -d "$local_root/packages/access-core" ]]; then
    fail "Local package path not found: $local_root/packages/access-core"
  fi

  log_step "Configuring local path repositories from $local_root/packages"
  composer config minimum-stability dev
  composer config prefer-stable true

  composer config --json repositories.aurora-access-core "{\"type\":\"path\",\"url\":\"$local_root/packages/access-core\",\"options\":{\"symlink\":true}}"
  composer config --json repositories.aurora-access-adapter-edgelink "{\"type\":\"path\",\"url\":\"$local_root/packages/access-adapter-edgelink\",\"options\":{\"symlink\":true}}"
  composer config --json repositories.aurora-access-adapter-modbus "{\"type\":\"path\",\"url\":\"$local_root/packages/access-adapter-modbus\",\"options\":{\"symlink\":true}}"
  composer config --json repositories.aurora-access-adapter-opc "{\"type\":\"path\",\"url\":\"$local_root/packages/access-adapter-opc\",\"options\":{\"symlink\":true}}"
  composer config --json repositories.aurora-access-adapter-serial-wiegand "{\"type\":\"path\",\"url\":\"$local_root/packages/access-adapter-serial-wiegand\",\"options\":{\"symlink\":true}}"

  if [[ "$CORE_VERSION" == "^1.0" ]]; then
    CORE_VERSION="dev-main"
  fi

  if [[ "$CORE_VERSION" == dev-* ]]; then
    core_package_constraint="*@dev"
  else
    core_package_constraint="$CORE_VERSION"
  fi

  adapter_package_constraint="dev-main"
fi

log_step "Requiring Aurora Access core"
composer require "otghcloud/aurora-access-core:${core_package_constraint}" --with-all-dependencies

if [[ "$WITH_ADAPTERS" == "true" ]]; then
  log_step "Requiring Aurora Access adapters"
  composer require \
    "otghcloud/aurora-access-adapter-edgelink:${adapter_package_constraint}" \
    "otghcloud/aurora-access-adapter-modbus:${adapter_package_constraint}" \
    "otghcloud/aurora-access-adapter-opc:${adapter_package_constraint}" \
    "otghcloud/aurora-access-adapter-serial-wiegand:${adapter_package_constraint}" \
    --with-all-dependencies
fi

ENV_FILE=".env"

set_env_value "$ENV_FILE" APP_NAME "$(quote_env_value "$app_name")"
set_env_value "$ENV_FILE" APP_URL "$(quote_env_value "$app_url")"
set_env_value "$ENV_FILE" DB_CONNECTION "$(quote_env_value "$db_connection")"
set_env_value "$ENV_FILE" DB_DATABASE "$(quote_env_value "$db_name")"

if [[ "$db_connection" == "sqlite" ]]; then
  mkdir -p "$(dirname "$db_name")"
  touch "$db_name"
else
  set_env_value "$ENV_FILE" DB_HOST "$(quote_env_value "$db_host")"
  set_env_value "$ENV_FILE" DB_PORT "$(quote_env_value "$db_port")"
  set_env_value "$ENV_FILE" DB_USERNAME "$(quote_env_value "$db_username")"
  set_env_value "$ENV_FILE" DB_PASSWORD "$(quote_env_value "$db_password")"
fi

set_env_value "$ENV_FILE" REDIS_HOST "$(quote_env_value "$redis_host")"
set_env_value "$ENV_FILE" REDIS_PORT "$(quote_env_value "$redis_port")"
set_env_value "$ENV_FILE" REDIS_PASSWORD "$(nullable_env_value "$redis_password")"
set_env_value "$ENV_FILE" MQTT_HOST "$(quote_env_value "$mqtt_host")"
set_env_value "$ENV_FILE" MQTT_PORT "$(quote_env_value "$mqtt_port")"
set_env_value "$ENV_FILE" MQTT_CLIENT_ID "$(quote_env_value "$mqtt_client_id")"
set_env_value "$ENV_FILE" MQTT_MONITOR_CONNECTION "$(quote_env_value "$mqtt_monitor_connection")"
set_env_value "$ENV_FILE" MQTT_MONITOR_CLIENT_ID "$(quote_env_value "$mqtt_monitor_client_id")"
set_env_value "$ENV_FILE" MQTT_AUTH_USERNAME "$(nullable_env_value "$mqtt_username")"
set_env_value "$ENV_FILE" MQTT_AUTH_PASSWORD "$(nullable_env_value "$mqtt_password")"
set_env_value "$ENV_FILE" MQTT_ENABLE_LOGGING "false"
set_env_value "$ENV_FILE" MQTT_AUTO_RECONNECT_ENABLED "true"
set_env_value "$ENV_FILE" MQTT_KEEP_ALIVE_INTERVAL "10"
set_env_value "$ENV_FILE" MQTT_CLEAN_SESSION "false"

log_step "Running database migrations"
php artisan migrate --force

log_step "Creating initial admin user"
php artisan app:create-initial-admin-user \
  --name="$admin_name" \
  --email="$admin_email" \
  --password="$admin_password"

log_step "Installing frontend dependencies"
npm install --ignore-scripts
npm run build

log_step "Host app scaffold complete"
echo "Installed Aurora Access host app at: $TARGET_DIR"
echo "Initial admin email: $admin_email"
