#!/usr/bin/env bash
#
# Puts an uploaded release live. Installed as /usr/local/bin/sebule-activate.
#
# The deploy pipeline reaches this through an SSH key whose only permitted
# command is this script, so the one thing it can ask for is "activate the
# release with this commit id".

set -euo pipefail

BASE=/var/www/sebule.jeffgicharu.com
APP_USER=sebule
PHP=/usr/bin/php8.4
KEEP_RELEASES=5

release_id="${SSH_ORIGINAL_COMMAND:-${1:-}}"

if [[ ! "$release_id" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Expected a 40 character commit id." >&2
    exit 64
fi

release="$BASE/releases/$release_id"

if [[ ! -f "$release/artisan" ]]; then
    echo "Release $release_id has not been uploaded." >&2
    exit 66
fi

cd "$release"

artisan() {
    sudo -u "$APP_USER" -- "$PHP" artisan "$@"
}

# One deploy at a time.
exec 9>"$BASE/.activate.lock"
flock -n 9 || { echo "Another activation is running." >&2; exit 75; }

echo "Linking shared files"
rm -rf "$release/storage"
ln -sfn "$BASE/shared/storage" "$release/storage"
ln -sfn "$BASE/shared/.env" "$release/.env"
ln -sfn "$BASE/shared/storage/app/public" "$release/public/storage"
sudo chown -R "$APP_USER:$APP_USER" "$release/bootstrap/cache"

echo "Migrating the database"
artisan migrate --force

echo "Caching configuration, routes and views"
artisan optimize

echo "Switching to release ${release_id:0:7}"
ln -sfn "$release" "$BASE/current.new"
mv -Tf "$BASE/current.new" "$BASE/current"

sudo systemctl reload php8.4-fpm
sudo systemctl restart sebule-queue.service
sudo systemctl start sebule-scheduler.timer

echo "Removing old releases"
cd "$BASE/releases"
ls -1t | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
    [[ "$old" =~ ^[0-9a-f]{40}$ && "$old" != "$release_id" ]] || continue
    sudo rm -rf -- "$BASE/releases/$old"
done

echo "Release ${release_id:0:7} is live."
