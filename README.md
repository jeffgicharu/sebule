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
npm install
npm run build
composer dev                # serves the app and rebuilds assets as you edit
```

The shop is at http://localhost:8000 and the back office at http://localhost:8000/admin.

## Tests

The tests run against PostgreSQL, in a database named `sebule_test` that the same user can reach.

```sh
composer test
```

Code style is checked with Pint:

```sh
vendor/bin/pint --test
```

## Configuration

| Variable | Purpose |
|---|---|
| `APP_TIMEZONE` | Defaults to `Africa/Nairobi`. |
| `STORE_ALLOW_INDEXING` | Set to `true` to let search engines index the site. Off by default. |
| `APP_RELEASE` | Overrides the release reported in the `X-Release` response header. Normally read from the `REVISION` file that the deploy writes. |
