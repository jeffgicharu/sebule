#!/usr/bin/env bash
#
# One-time server setup for sebule.jeffgicharu.com on Ubuntu 22.04.
# Run as root from a copy of this directory. Safe to run again: it never
# overwrites the environment file and never touches another site.
#
#   sudo bash provision.sh

set -euo pipefail

SITE=sebule.jeffgicharu.com
APP_USER=sebule
DB_NAME=sebule
BASE=/var/www/$SITE
PHP_VERSION=8.4
HERE="$(cd "$(dirname "$0")" && pwd)"

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_SUSPEND=1

echo "== PHP $PHP_VERSION"
if ! apt-cache policy "php$PHP_VERSION-fpm" | grep -q 'Candidate: [0-9]'; then
    apt-get install -y -q software-properties-common
    add-apt-repository -y ppa:ondrej/php
    apt-get update -q
fi
apt-get install -y -q --no-install-recommends \
    "php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-pgsql" \
    "php$PHP_VERSION-mbstring" "php$PHP_VERSION-xml" "php$PHP_VERSION-curl" \
    "php$PHP_VERSION-intl" "php$PHP_VERSION-gd" "php$PHP_VERSION-zip" \
    "php$PHP_VERSION-bcmath" "php$PHP_VERSION-redis" "php$PHP_VERSION-opcache"

echo "== Application user and directories"
if ! id -u "$APP_USER" >/dev/null 2>&1; then
    useradd --system --home-dir "$BASE" --no-create-home --shell /usr/sbin/nologin "$APP_USER"
fi

install -d -o deploy -g deploy -m 755 "$BASE" "$BASE/releases"
install -d -o "$APP_USER" -g "$APP_USER" -m 755 \
    "$BASE/shared" "$BASE/shared/storage" "$BASE/shared/storage/app" "$BASE/shared/storage/app/public"
install -d -o "$APP_USER" -g "$APP_USER" -m 750 \
    "$BASE/shared/storage/app/private" \
    "$BASE/shared/storage/framework" "$BASE/shared/storage/framework/cache" \
    "$BASE/shared/storage/framework/cache/data" "$BASE/shared/storage/framework/sessions" \
    "$BASE/shared/storage/framework/views" "$BASE/shared/storage/logs"

echo "== Database"
role_exists() { sudo -u postgres psql -Atc "select 1 from pg_roles where rolname='$APP_USER'" | grep -q 1; }
db_exists() { sudo -u postgres psql -Atc "select 1 from pg_database where datname='$DB_NAME'" | grep -q 1; }

if [[ ! -f "$BASE/shared/.env" ]]; then
    db_password="$(openssl rand -hex 24)"
    if role_exists; then
        sudo -u postgres psql -q -v pw="$db_password" <<<"ALTER ROLE $APP_USER LOGIN PASSWORD :'pw';"
    else
        sudo -u postgres psql -q -v pw="$db_password" <<<"CREATE ROLE $APP_USER LOGIN PASSWORD :'pw';"
    fi

    echo "== Environment file"
    umask 077
    cat > "$BASE/shared/.env" <<EOF
APP_NAME=Sebule
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://$SITE
APP_TIMEZONE=Africa/Nairobi

LOG_CHANNEL=daily
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=$DB_NAME
DB_USERNAME=$APP_USER
DB_PASSWORD=$db_password

# Redis is shared with other projects on this server. Index 1 holds queues
# and sessions, index 2 holds the cache. Clearing the cache empties index 2.
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=1
REDIS_CACHE_DB=2
REDIS_PREFIX=sebule:

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=log

STORE_ALLOW_INDEXING=false
EOF
    umask 022
    chown "$APP_USER:$APP_USER" "$BASE/shared/.env"
    chmod 600 "$BASE/shared/.env"
    unset db_password
else
    echo "Keeping the existing environment file."
fi

db_exists || sudo -u postgres createdb -O "$APP_USER" "$DB_NAME"
sudo -u postgres psql -q -d "$DB_NAME" -c 'CREATE EXTENSION IF NOT EXISTS pg_trgm; CREATE EXTENSION IF NOT EXISTS unaccent;'

echo "== PHP-FPM pool"
pool_dir="/etc/php/$PHP_VERSION/fpm/pool.d"
install -o root -g root -m 644 "$HERE/php-fpm.conf" "$pool_dir/sebule.conf"
# The stock pool serves nothing here. Each site runs in its own pool.
[[ -f "$pool_dir/www.conf" ]] && mv "$pool_dir/www.conf" "$pool_dir/www.conf.disabled"
"php-fpm$PHP_VERSION" --test
systemctl enable "php$PHP_VERSION-fpm" >/dev/null
systemctl restart "php$PHP_VERSION-fpm"

echo "== Activation script and services"
install -o root -g root -m 755 "$HERE/activate.sh" /usr/local/bin/sebule-activate
install -o root -g root -m 644 "$HERE/sebule-queue.service" /etc/systemd/system/sebule-queue.service
install -o root -g root -m 644 "$HERE/sebule-scheduler.service" /etc/systemd/system/sebule-scheduler.service
install -o root -g root -m 644 "$HERE/sebule-scheduler.timer" /etc/systemd/system/sebule-scheduler.timer
systemctl daemon-reload
systemctl enable sebule-queue.service sebule-scheduler.timer >/dev/null

echo "== nginx"
install -o root -g root -m 644 "$HERE/nginx.conf" "/etc/nginx/sites-available/$SITE"
ln -sfn "/etc/nginx/sites-available/$SITE" "/etc/nginx/sites-enabled/$SITE"
if nginx -t; then
    systemctl reload nginx
else
    # A broken config must not stay enabled: the next reload would take
    # every site on the server down with it.
    rm -f "/etc/nginx/sites-enabled/$SITE"
    echo "nginx rejected the site config. It has been disabled again." >&2
    exit 1
fi

echo "== Done"
