#!/usr/bin/env bash
set -euo pipefail

# eTeam Manager - Secure permissions & basic hardening
# - Applies least-privilege filesystem permissions
# - Creates a shared group so both the admin user and Apache can read
# - Optionally marks runtime-writable folders as writable by Apache
# - Enables a small Apache hardening config (ServerTokens/Signature/Trace)
#
# Usage:
#   sudo ./scripts/02_secure_permissions.sh
#   sudo DEPLOY_DIR=/var/www/eteam-manager ADMIN_USER=ubuntu ./scripts/02_secure_permissions.sh

require_root() {
  if [[ ${EUID:-0} -ne 0 ]]; then
    echo "ERROR: Run as root (use sudo)." >&2
    exit 1
  fi
}

log() {
  echo "[secure] $*"
}

# ----------------------
# Config (override via env)
# ----------------------
APP_NAME=${APP_NAME:-"eteam-manager"}
DEPLOY_DIR=${DEPLOY_DIR:-"/var/www/${APP_NAME}"}

# Admin user to grant group access (defaults to the sudo invoker when available)
ADMIN_USER=${ADMIN_USER:-"${SUDO_USER:-}"}

# Group ownership for the app files. Defaulting to Apache's primary group avoids
# relying on supplementary groups (which can cause 403s depending on service setup).
WEB_GROUP=${WEB_GROUP:-"www-data"}

# Space-separated list of relative dirs that should be writable by Apache if they exist
# (Project currently doesn't need writable dirs, but this keeps it future-proof.)
WRITABLE_DIRS=${WRITABLE_DIRS:-"public/uploads storage var cache"}

APACHE_HARDENING_CONF="/etc/apache2/conf-available/${APP_NAME}-hardening.conf"

main() {
  require_root

  if [[ ! -d "${DEPLOY_DIR}" ]]; then
    echo "ERROR: DEPLOY_DIR not found: ${DEPLOY_DIR}" >&2
    echo "Run the setup script first, or set DEPLOY_DIR." >&2
    exit 1
  fi

  if [[ -n "${ADMIN_USER}" ]] && id "${ADMIN_USER}" >/dev/null 2>&1; then
    log "Ensuring admin user ${ADMIN_USER} is in ${WEB_GROUP}..."
    usermod -a -G "${WEB_GROUP}" "${ADMIN_USER}" || true
  fi

  log "Setting ownership to root:${WEB_GROUP} for ${DEPLOY_DIR}..."
  chown -R root:"${WEB_GROUP}" "${DEPLOY_DIR}"

  log "Applying default permissions (dirs 750, files 640)..."
  find "${DEPLOY_DIR}" -type d -exec chmod 750 {} +
  find "${DEPLOY_DIR}" -type f -exec chmod 640 {} +

  # Ensure entrypoints are readable/executable for Apache traversal
  chmod 750 "${DEPLOY_DIR}" || true

  # Lock down database SQL files more tightly (after initial import)
  if [[ -d "${DEPLOY_DIR}/database" ]]; then
    log "Locking down database/ (root-only read)..."
    chown -R root:root "${DEPLOY_DIR}/database"
    find "${DEPLOY_DIR}/database" -type d -exec chmod 700 {} +
    find "${DEPLOY_DIR}/database" -type f -exec chmod 600 {} +
  fi

  # Mark specific runtime-writable folders if they exist
  for rel in ${WRITABLE_DIRS}; do
    if [[ -d "${DEPLOY_DIR}/${rel}" ]]; then
      log "Making ${rel} writable by Apache (www-data) ..."
      chown -R www-data:"${WEB_GROUP}" "${DEPLOY_DIR}/${rel}"
      find "${DEPLOY_DIR}/${rel}" -type d -exec chmod 770 {} +
      find "${DEPLOY_DIR}/${rel}" -type f -exec chmod 660 {} +
    fi
  done

  log "Writing Apache hardening conf: ${APACHE_HARDENING_CONF}..."
  cat >"${APACHE_HARDENING_CONF}" <<EOF
# Basic Apache hardening (safe defaults)
ServerTokens Prod
ServerSignature Off
TraceEnable Off

<IfModule mod_headers.c>
  Header unset X-Powered-By
</IfModule>
EOF

  log "Enabling Apache hardening conf and reloading..."
  a2enconf "${APP_NAME}-hardening" >/dev/null
  apache2ctl configtest
  systemctl reload apache2

  log "Done. Note: You may need to re-login for new group memberships to apply."
}

main "$@"
