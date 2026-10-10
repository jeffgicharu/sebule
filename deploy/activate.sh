#!/usr/bin/env bash
#
# Puts an uploaded release live. Installed as /usr/local/bin/sebule-activate.
#
# The deploy pipeline reaches this through an SSH key whose only permitted
# command is this script, so the one thing it can ask for is "activate the
# release with this commit id".
#
# A release is checked twice. Before the switch it opens its own pages while
# the site is still on the old release, and is not put live if any of them
# fails. After the switch the pages are fetched through the web server, and
# the site goes back to the release it was on if they do not load.
#
# To go back by hand, run this script with the commit id of an earlier
# release that is still in the releases directory.

set -euo pipefail

BASE=/var/www/sebule.jeffgicharu.com
HOST=sebule.jeffgicharu.com
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

# The gate. The new release opens the pages a visitor can reach, using the live
# configuration, database and cache. Nothing has been switched yet, so a release
# that fails here never reaches a customer.
echo "Checking the release before it goes live"
if ! artisan release:check; then
    echo "Release ${release_id:0:7} failed its check and was not put live." >&2
    exit 1
fi

previous="$(readlink -f "$BASE/current" 2>/dev/null || true)"

switch_to() {
    ln -sfn "$1" "$BASE/current.new"
    mv -Tf "$BASE/current.new" "$BASE/current"
    sudo systemctl reload php8.4-fpm
}

# Asks the web server on this machine for a page under the public name. The
# certificate is the one Cloudflare trusts, not one curl knows, hence --insecure.
fetch() {
    curl --silent --show-error --insecure --max-time 20 \
        --resolve "$HOST:443:127.0.0.1" "$@"
}

serves_the_release() {
    local attempt headers path

    # PHP is reloading, so allow it a few seconds to answer with the new release.
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        headers="$(fetch --head "https://$HOST/up" || true)"
        if grep -qi "^x-release: $release_id" <<<"$headers"; then
            break
        fi
        if [[ $attempt -eq 10 ]]; then
            echo "The web server is not serving ${release_id:0:7}." >&2
            return 1
        fi
        sleep 1
    done

    # The home page twice: the second visit reads back what the first one cached.
    for path in / / /sitemap.xml /admin/login; do
        if ! fetch --fail --output /dev/null "https://$HOST$path"; then
            echo "$path did not load on the live site." >&2
            return 1
        fi
    done
}

echo "Switching to release ${release_id:0:7}"
switch_to "$release"

echo "Checking the live site"
if ! serves_the_release; then
    if [[ -n "$previous" && -d "$previous" && "$previous" != "$release" ]]; then
        switch_to "$previous"
        echo "Release ${release_id:0:7} did not serve its pages. The site is back on ${previous##*/}." >&2
    else
        echo "Release ${release_id:0:7} did not serve its pages and there is no earlier release to go back to." >&2
    fi
    exit 1
fi

sudo systemctl restart sebule-queue.service
sudo systemctl start sebule-scheduler.timer

echo "Removing old releases"
cd "$BASE/releases"
ls -1t | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
    [[ "$old" =~ ^[0-9a-f]{40}$ && "$old" != "$release_id" && "$BASE/releases/$old" != "$previous" ]] || continue
    sudo rm -rf -- "$BASE/releases/$old"
done

echo "Release ${release_id:0:7} is live."
