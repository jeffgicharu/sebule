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
php artisan db:seed         # creates the categories and the catalogue
npm install
npm run build
composer dev                # serves the app and rebuilds assets as you edit
```

The shop is at http://localhost:8000 and the back office at http://localhost:8000/admin.

## Staff accounts

Staff sign in to the back office with their own accounts, which are separate from customer accounts. The roles are `owner`, `manager`, `fulfilment` and `support`.

Owners add and manage staff in the back office under Settings, Staff. Accounts are switched off when someone leaves, never deleted, so stock history keeps the name of whoever made each change.

The first owner has to be created from the command line:

```sh
php artisan staff:create "Wanjiru Kamau" wanjiru@example.com --role=owner
```

The command prints a generated password once; only its hash is stored.

## Seeding

`php artisan db:seed` creates the category tree and then the catalogue in `database/seeders/data/catalogue.json`: 8 collections and 80 products with their options, variants, prices and opening stock. Both seeders are safe to run again. Anything whose web address name already exists is left as it is, so changes made in the back office are never overwritten.

Product photos are not kept in the repository. With the files in a directory, named `<photo id>_<option value>_<shot>.jpg` (for example `amani-sofa-3s_sand_studio-34.jpg`), attach them with:

```sh
php artisan catalogue:import-photos /path/to/photos
```

A room shot is attached to the product; every other shot is attached to the colour or finish it shows. The command can be run again and only adds what is missing.

## How the catalogue is modelled

- A **product** belongs to one category and has **options** such as Colour and Size, each with its own choices.
- A **variant** is one buyable combination of those choices, with its own SKU, price and stock. The back office generates the combinations.
- Prices are whole numbers of cents. Nothing in the application holds money as a float.
- Stock changes only through `App\Inventory\StockLedger`. It locks the variant's row, refuses to go below zero, and writes a movement recording who changed it, why, and the balance left. The database has its own check constraint as a second line of defence. Staff read the movements under Catalogue, Stock history; they cannot be edited.

## From cart to paid

A cart belongs to a browser: a cookie holds its token and nothing else. It is created when the first piece is added, and it flags a line whose price has moved or whose stock has run short since.

Checkout is two pages that work without script: the customer's details, then a review with the delivery fee and the total. Placing the order runs in one transaction (`App\Checkout\PlaceOrder`): the order and its lines are written with their own copy of names and prices, stock is taken through the stock ledger, and the cart is deleted. The form carries a key, and the same key always gives the same order, so a double tap or a retry never makes a second one.

Stock is held for `STORE_HOLD_MINUTES`. `orders:release-unpaid` runs every minute and returns the stock of orders nobody paid for; the customer can pick such an order up again from its link if the pieces are still there.

Paying is an M-Pesa prompt (`App\Payments\StartMpesaPayment`). Safaricom's confirmation is posted to an address signed for that one prompt, must name that prompt and must be for the amount asked; it is recorded once however many times it arrives. If no confirmation comes, a queued job asks Safaricom what became of the prompt and settles the order either way. Every order page is reached by a secret link, so tracking needs no account.

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
4. **Activate.** A second key can run one script, `deploy/activate.sh`, and nothing else. It links the shared storage and environment file and migrates the database. Then the release checks itself: `php artisan release:check` starts at the home page and opens the pages a visitor can reach, using the live configuration, database and cache. Only if every page loads is the `current` link switched to the new release. A release that fails is left where it is and the site carries on with the one before.
5. **Verify.** The script fetches the pages again through nginx. If they do not load, it switches back to the previous release. The workflow then reads the `X-Release` header from the live site, fails unless it matches the commit, and loads the main pages from outside.

Each release has its own cache entries (the cache prefix ends with the commit), so new code never reads data cached by older code.

The last five releases are kept on the server. To go back to one of them, run `sudo -u deploy /usr/local/bin/sebule-activate <commit>` there.

The `deploy/` directory holds everything the server runs with: the nginx site, the PHP-FPM pool, the systemd units for the queue worker and the scheduler, and `provision.sh`, which sets a new server up from scratch.

The workflow needs these repository secrets: `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_ACTIVATE_SSH_KEY` and `VPS_KNOWN_HOSTS`.

## Configuration

| Variable | Purpose |
|---|---|
| `APP_TIMEZONE` | Defaults to `Africa/Nairobi`. |
| `STORE_ALLOW_INDEXING` | Set to `true` to let search engines index the site. Off by default. |
| `STORE_HOLD_MINUTES` | How long stock is held for an order while its customer pays. 15 by default. |
| `STORE_TEST_PAYMENTS` | Set to `true` to let an order be completed with a test payment, with no money moving. Off by default. |
| `DARAJA_ENV` | `sandbox` or `production`. |
| `DARAJA_CONSUMER_KEY`, `DARAJA_CONSUMER_SECRET`, `DARAJA_PASSKEY`, `DARAJA_SHORTCODE` | The store's Safaricom Daraja app. Without them the M-Pesa option is not offered. |
| `MPESA_CHARGE_SHILLINGS` | A fixed number of shillings to ask for whatever the order comes to. Empty asks for the order's total. |
| `APP_RELEASE` | Overrides the release reported in the `X-Release` response header. Normally read from the `REVISION` file that the deploy writes. |
