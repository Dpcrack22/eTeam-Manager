#!/usr/bin/env bash
set -euo pipefail

# eTeam Manager - Ubuntu Server Setup (Apache + PHP + MySQL)
# - Installs required packages
# - Deploys this repo to /var/www
# - Creates an Apache vhost
# - (Optional) Creates DB schema + app DB user + dev seed
#
# Usage (examples):
#   sudo ./scripts/01_setup_ubuntu_server.sh
#   sudo SERVER_NAME=eteam.example.com SEED_DEV=1 ./scripts/01_setup_ubuntu_server.sh
#   sudo MYSQL_APP_PASSWORD='strongpass' ./scripts/01_setup_ubuntu_server.sh

require_root() {
  if [[ ${EUID:-0} -ne 0 ]]; then
    echo "ERROR: Run as root (use sudo)." >&2
    exit 1
  fi
}

log() {
  echo "[setup] $*"
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1
}

# ----------------------
# Config (override via env)
# ----------------------
APP_NAME=${APP_NAME:-"eteam-manager"}
DEPLOY_DIR=${DEPLOY_DIR:-"/var/www/${APP_NAME}"}
SOURCE_DIR=${SOURCE_DIR:-"$(pwd)"}

SERVER_NAME=${SERVER_NAME:-"${APP_NAME}.local"}
SERVER_ADMIN=${SERVER_ADMIN:-"webmaster@localhost"}

MYSQL_DB_NAME=${MYSQL_DB_NAME:-"eteam_manager"}
MYSQL_APP_USER=${MYSQL_APP_USER:-"eteam_app"}
MYSQL_APP_HOST=${MYSQL_APP_HOST:-"localhost"}
MYSQL_APP_PASSWORD=${MYSQL_APP_PASSWORD:-""}
MYSQL_APP_PRIVILEGES=${MYSQL_APP_PRIVILEGES:-"SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES,TRIGGER"}

IMPORT_DB_SCHEMA=${IMPORT_DB_SCHEMA:-1}
CREATE_DB_USER=${CREATE_DB_USER:-1}
SEED_DEV=${SEED_DEV:-0}

# ----------------------
# Derived paths
# ----------------------
VHOST_FILE="/etc/apache2/sites-available/${APP_NAME}.conf"

main() {
  require_root

  if [[ ! -f "${SOURCE_DIR}/app.php" || ! -f "${SOURCE_DIR}/index.php" ]]; then
    echo "ERROR: SOURCE_DIR doesn't look like the project root." >&2
    echo "Set SOURCE_DIR to the folder that contains app.php and index.php." >&2
    exit 1
  fi

  log "Updating apt and installing packages (Apache/PHP/MySQL)..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y \
    apache2 \
    mysql-server \
    php \
    libapache2-mod-php \
    php-cli \
    php-mysql \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    unzip \
    git \
    rsync \
    ca-certificates

  log "Enabling required Apache modules..."
  a2enmod rewrite headers ssl >/dev/null

  log "Deploying project to ${DEPLOY_DIR}..."
  mkdir -p "${DEPLOY_DIR}"
  rsync -a --delete \
    --exclude ".git" \
    --exclude ".github" \
    --exclude "node_modules" \
    "${SOURCE_DIR}/" "${DEPLOY_DIR}/"

  log "Preparing runtime upload directories..."
  mkdir -p "${DEPLOY_DIR}/uploads/avatars"
  chown -R www-data:www-data "${DEPLOY_DIR}/uploads"
  find "${DEPLOY_DIR}/uploads" -type d -exec chmod 2775 {} +
  find "${DEPLOY_DIR}/uploads" -type f -exec chmod 664 {} +

  log "Creating Apache vhost at ${VHOST_FILE}..."
  cat >"${VHOST_FILE}" <<EOF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    ServerAdmin ${SERVER_ADMIN}

    DocumentRoot ${DEPLOY_DIR}

    ErrorLog \${APACHE_LOG_DIR}/${APP_NAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${APP_NAME}-access.log combined

    <Directory ${DEPLOY_DIR}>
        Options -Indexes
        AllowOverride None
        Require all granted

        # Block common non-public files if they exist in the docroot
        <FilesMatch "(?i)\\.(sql|sqlite|db|bak|old|log|ini|env|txt|md)$">
            Require all denied
        </FilesMatch>
    </Directory>

    # Do not allow direct web access to internal folders (PHP can still include them)
    <Directory ${DEPLOY_DIR}/database>
        Require all denied
    </Directory>
    <Directory ${DEPLOY_DIR}/includes>
        Require all denied
    </Directory>

    # Basic security headers (minimal, safe defaults)
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "same-origin"
</VirtualHost>
EOF

  log "Enabling site and reloading Apache..."
  a2dissite 000-default >/dev/null 2>&1 || true
  a2ensite "${APP_NAME}.conf" >/dev/null
  apache2ctl configtest
  systemctl reload apache2

  if [[ "${IMPORT_DB_SCHEMA}" -eq 1 || "${CREATE_DB_USER}" -eq 1 || "${SEED_DEV}" -eq 1 ]]; then
    log "Configuring MySQL (schema/user/seed as requested)..."
    if ! need_cmd mysql; then
      echo "ERROR: mysql client not found (should be installed with mysql-server)." >&2
      exit 1
    fi

    if [[ "${IMPORT_DB_SCHEMA}" -eq 1 ]]; then
      if [[ ! -f "${DEPLOY_DIR}/database/01_create_database.sql" ]]; then
        echo "ERROR: Missing database/01_create_database.sql in deploy directory." >&2
        exit 1
      fi

      log "Creating database ${MYSQL_DB_NAME} if needed..."
      mysql -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DB_NAME}\` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;"

      log "Importing schema into ${MYSQL_DB_NAME} from database/01_create_database.sql (idempotent)..."
      mysql "${MYSQL_DB_NAME}" < "${DEPLOY_DIR}/database/01_create_database.sql"
    fi

    if [[ "${CREATE_DB_USER}" -eq 1 ]]; then
      if [[ -z "${MYSQL_APP_PASSWORD}" ]]; then
        log "MYSQL_APP_PASSWORD not set; generating a random one and printing it once."
        MYSQL_APP_PASSWORD="$(tr -dc 'A-Za-z0-9!@#%^*()-_=+' </dev/urandom | head -c 28)"
        echo "MYSQL_APP_PASSWORD=${MYSQL_APP_PASSWORD}"
      fi

      # Escape single quotes for SQL string literals
      MYSQL_APP_PASSWORD_SQL=$(printf "%s" "${MYSQL_APP_PASSWORD}" | sed "s/'/\\\\'/g")

      log "Creating/updating DB user ${MYSQL_APP_USER}@${MYSQL_APP_HOST} with configured privileges..."
      mysql -e "CREATE USER IF NOT EXISTS '${MYSQL_APP_USER}'@'${MYSQL_APP_HOST}' IDENTIFIED BY '${MYSQL_APP_PASSWORD_SQL}';" || true
      mysql -e "ALTER USER '${MYSQL_APP_USER}'@'${MYSQL_APP_HOST}' IDENTIFIED BY '${MYSQL_APP_PASSWORD_SQL}';" || true
      mysql -e "GRANT ${MYSQL_APP_PRIVILEGES} ON \`${MYSQL_DB_NAME}\`.* TO '${MYSQL_APP_USER}'@'${MYSQL_APP_HOST}';"
      mysql -e "FLUSH PRIVILEGES;"
    fi

    if [[ "${SEED_DEV}" -eq 1 ]]; then
      if [[ ! -f "${DEPLOY_DIR}/database/03_seed_dev_valorant.sql" ]]; then
        echo "ERROR: Missing database/03_seed_dev_valorant.sql in deploy directory." >&2
        exit 1
      fi

      log "Seeding DEV data from database/03_seed_dev_valorant.sql (DEV ONLY)..."
      mysql < "${DEPLOY_DIR}/database/03_seed_dev_valorant.sql"
    fi
  fi

  log "Done. Try opening: http://${SERVER_NAME}/ (or your server IP)"
  log "If DNS/hosts isn't set for SERVER_NAME, use http://<server-ip>/"
}

main "$@"
