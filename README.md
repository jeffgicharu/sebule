# Sebule

The online store for Sebule, a Kenyan furniture and home brand. One Laravel application serves the shop that customers use and the back office that staff use.

## Stack

- PHP 8.4 and Laravel 13
- Livewire 4 and Tailwind CSS 4 for the shop
- Filament 5 for the back office, at `/admin`
- PostgreSQL 16 or newer

## Running it locally

You need PHP 8.4 with the `pgsql`, `mbstring`, `xml`, `curl`, `intl`, `gd`, `zip` and `bcmath` extensions, Composer, Node.js 22 and PostgreSQL.

Create a database and a user for it, then:

```sh
cp .env.example .env        # then set DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed         # creates the shop's categories
npm install
npm run build
composer dev                # serves the app and rebuilds assets as you edit
```

The shop is at http://localhost:8000 and the back office at http://localhost:8000/admin.

## Staff accounts

Staff sign in to the back office with their own accounts, which are separate from customer accounts. Create one from the command line:

```sh
php artisan staff:create "Wanjiru Kamau" wanjiru@example.com --role=manager
```

The roles are `owner`, `manager`, `fulfilment` and `support`. The command prints a generated password once; only its hash is stored.

## How the catalogue is modelled

- A **product** belongs to one category and has **options** such as Colour and Size, each with its own choices.
- A **variant** is one buyable combination of those choices, with its own SKU, price and stock. The back office generates the combinations.
- Prices are whole numbers of cents. Nothing in the application holds money as a float.
- Stock changes only through `App\Inventory\StockLedger`. It locks the variant's row, refuses to go below zero, and writes a movement recording who changed it, why, and the balance left. The database has its own check constraint as a second line of defence.

## Tests

The tests run against PostgreSQL, in a database named `sebule_test` that the same user can reach.

```sh
composer test
```

Code style is checked with Pint:

```sh
vendor/bin/pint --test
```

## Deployment

Every push to `main` runs `.github/workflows/deploy.yml`, which tests the code, builds a release and puts it live at https://sebule.jeffgicharu.com.

1. **Test.** Pint and the test suite run against PostgreSQL 16.
2. **Build.** Composer dependencies without dev packages and the Vite assets are built in the workflow, so the server needs neither Composer nor Node.
3. **Upload.** The release is copied to `releases/<commit>` on the server with a key that can only rsync into that directory.
4. **Activate.** A second key can run one script, `deploy/activate.sh`, and nothing else. It links the shared storage and environment file, migrates the database, switches the `current` link to the new release, reloads PHP-FPM and restarts the queue worker.
5. **Verify.** The workflow reads the `X-Release` header from the live site and fails unless it matches the commit.

The last five releases are kept on the server. To go back to one of them, run `sudo -u deploy /usr/local/bin/sebule-activate <commit>` there.

The `deploy/` directory holds everything the server runs with: the nginx site, the PHP-FPM pool, the systemd units for the queue worker and the scheduler, and `provision.sh`, which sets a new server up from scratch.

The workflow needs these repository secrets: `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_ACTIVATE_SSH_KEY` and `VPS_KNOWN_HOSTS`.

## Configuration

| Variable | Purpose |
|---|---|
| `APP_TIMEZONE` | Defaults to `Africa/Nairobi`. |
| `STORE_ALLOW_INDEXING` | Set to `true` to let search engines index the site. Off by default. |
| `APP_RELEASE` | Overrides the release reported in the `X-Release` response header. Normally read from the `REVISION` file that the deploy writes. |
