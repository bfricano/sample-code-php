#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════
#  REGPE — VPS Deployment Script
#  Tested on Ubuntu 22.04 / 24.04
#
#  Usage:
#    # First time — run on the server as root or with sudo:
#    bash deploy.sh install
#
#    # Subsequent deploys (pull & reload):
#    bash deploy.sh update
# ═══════════════════════════════════════════════════════════

set -euo pipefail

DOMAIN="regpe.com"
APP_DIR="/var/www/regpe"
REPO="https://github.com/bfricano/sample-code-php.git"
BRANCH="master"
NGINX_CONF="/etc/nginx/sites-available/regpe"
PHP_SOCK="/run/php/php8.4-fpm.sock"

GREEN='\033[0;32m'; AMBER='\033[0;33m'; RED='\033[0;31m'; NC='\033[0m'
info()  { echo -e "${GREEN}▶${NC} $*"; }
warn()  { echo -e "${AMBER}⚠${NC}  $*"; }
error() { echo -e "${RED}✕${NC}  $*"; exit 1; }

# ── INSTALL (first-time setup) ───────────────────────────────
cmd_install() {
    [[ $EUID -ne 0 ]] && error "Run install as root: sudo bash deploy.sh install"

    info "Updating package index…"
    apt-get update -q

    info "Installing Nginx, PHP 8.4, SQLite…"
    apt-get install -y -q \
        nginx \
        php8.4-fpm php8.4-sqlite3 php8.4-opcache php8.4-mbstring php8.4-xml \
        sqlite3 \
        certbot python3-certbot-nginx \
        git curl

    info "Cloning repository…"
    mkdir -p "$APP_DIR"
    if [[ -d "$APP_DIR/.git" ]]; then
        warn "Directory already exists — pulling latest instead."
        git -C "$APP_DIR" pull origin "$BRANCH"
    else
        git clone --depth=1 --branch "$BRANCH" "$REPO" "$APP_DIR"
    fi

    info "Setting up data directory…"
    mkdir -p "$APP_DIR/TraderResiliencePlatform/data"
    chown -R www-data:www-data "$APP_DIR/TraderResiliencePlatform/data"
    chmod 750 "$APP_DIR/TraderResiliencePlatform/data"

    info "Installing Nginx config…"
    cp "$APP_DIR/TraderResiliencePlatform/nginx.conf" "$NGINX_CONF"
    # Temporarily serve over HTTP so Certbot can do the ACME challenge
    sed -i 's/return 301 https/# return 301 https/' "$NGINX_CONF"
    sed -i '/listen 443/,/^}/{ /listen 443/d }' "$NGINX_CONF" 2>/dev/null || true
    ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/regpe
    rm -f /etc/nginx/sites-enabled/default
    nginx -t && systemctl reload nginx

    info "Provisioning SSL certificate via Certbot…"
    certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
        --email "admin@${DOMAIN}" --redirect
    # Certbot patches the config; restore the full SSL block
    cp "$APP_DIR/TraderResiliencePlatform/nginx.conf" "$NGINX_CONF"
    ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/regpe
    nginx -t && systemctl reload nginx

    info "Configuring PHP-FPM…"
    PHP_INI="/etc/php/8.4/fpm/php.ini"
    sed -i 's/^;*opcache.enable\s*=.*/opcache.enable=1/'          "$PHP_INI"
    sed -i 's/^;*opcache.memory_consumption\s*=.*/opcache.memory_consumption=128/' "$PHP_INI"
    sed -i 's/^upload_max_filesize\s*=.*/upload_max_filesize=20M/' "$PHP_INI"
    sed -i 's/^post_max_size\s*=.*/post_max_size=20M/'             "$PHP_INI"
    systemctl restart php8.4-fpm

    info "Configuring log rotation…"
    cat > /etc/logrotate.d/regpe <<'LOGROTATE'
/var/log/nginx/regpe.*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    sharedscripts
    postrotate
        nginx -s reopen
    endscript
}
LOGROTATE

    echo ""
    echo -e "${GREEN}═══════════════════════════════════════════${NC}"
    echo -e "${GREEN}  REGPE is live at https://${DOMAIN}${NC}"
    echo -e "${GREEN}═══════════════════════════════════════════${NC}"
}

# ── UPDATE (redeploy latest) ─────────────────────────────────
cmd_update() {
    info "Pulling latest from ${BRANCH}…"
    git -C "$APP_DIR" fetch origin
    git -C "$APP_DIR" checkout "$BRANCH"
    git -C "$APP_DIR" pull origin "$BRANCH"

    info "Fixing data directory permissions…"
    chown -R www-data:www-data "$APP_DIR/TraderResiliencePlatform/data"

    info "Reloading PHP-FPM and Nginx…"
    systemctl reload php8.4-fpm
    nginx -t && systemctl reload nginx

    echo ""
    echo -e "${GREEN}✓ Deployed latest to https://${DOMAIN}${NC}"
}

# ── ENTRY POINT ───────────────────────────────────────────────
case "${1:-}" in
    install) cmd_install ;;
    update)  cmd_update  ;;
    *)
        echo "Usage: bash deploy.sh [install|update]"
        echo "  install — first-time server setup (run as root)"
        echo "  update  — pull latest and reload"
        exit 1
        ;;
esac
