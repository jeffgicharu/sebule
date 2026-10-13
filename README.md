# Sebule

The online store for Sebule, a Kenyan furniture and home brand. One Laravel application serves the shop that customers use and the back office that staff use.

## Stack

- PHP 8.4 and Laravel 13
- Blade templates and Tailwind CSS 4 for the shop, which works without script
- Filament 5 (on Livewire 4) for the back office, at `/admin`
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

| Role | Orders | Payments and customers | Stock | Catalogue, pages, home page, store settings, delivery zones | Staff |
|---|---|---|---|---|---|
| `owner` | Read, move on, cancel (paid or not), record refunds | Read | Count in and out | Change | Manage |
| `manager` | Read, move on, cancel unpaid | Read | Count in and out | Change | |
| `fulfilment` | Read, move on, cancel unpaid | | Count in and out | | |
| `support` | Read, cancel unpaid | Read | | | |

The rules live in `App\Enums\StaffRole` and `App\Policies\OrderPolicy`, and the server checks them on every action: an action a role may not take is neither shown nor accepted.

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

## Listings

A category page can be narrowed by price, colour or finish, material, width and availability, and sorted by price, newest or most popular (units paid for in the last 90 days). Every choice is in the address, so a filtered page can be shared, and the form works without script (`App\Catalogue\ProductListing`).

## Search

Search runs in PostgreSQL with the `pg_trgm` extension, so there is no search service to run beside the database. Each product carries a `search_text` column holding the words it might be looked for by: its name, category and room, collections, colours and finishes, materials and description, plus everyday words for the store's own colour names, so that "green" finds Forest and Olive. `App\Catalogue\SearchIndex` rewrites it whenever a product, a colour or a category name changes, and `catalogue:index-search` rewrites all of them each night.

`App\Catalogue\ProductSearch` requires every typed word to match a product, either as written or closely enough to be a misspelling ("soffa", "dinning table"). Words of three letters or fewer must match as written. Products whose own name matches are ranked first.

## From cart to paid

A cart belongs to a browser: a cookie holds its token and nothing else. It is created when the first piece is added, and it flags a line whose price has moved or whose stock has run short since.

Checkout is two pages that work without script: the customer's details, then a review with the delivery fee and the total. Placing the order runs in one transaction (`App\Checkout\PlaceOrder`): the order and its lines are written with their own copy of names and prices, stock is taken through the stock ledger, and the cart is deleted. The form carries a key, and the same key always gives the same order, so a double tap or a retry never makes a second one.

Stock is held for `STORE_HOLD_MINUTES`. `orders:release-unpaid` runs every minute and returns the stock of orders nobody paid for; the customer can pick such an order up again from its link if the pieces are still there.

Paying is an M-Pesa prompt (`App\Payments\StartMpesaPayment`). Safaricom's confirmation is posted to an address signed for that one prompt, must name that prompt and must be for the amount asked; it is recorded once however many times it arrives. If no confirmation comes, a queued job asks Safaricom what became of the prompt and settles the order either way. Only a result code the store knows to be final settles a payment that way (`App\Payments\Mpesa\MpesaResult::settles`): Safaricom also answers with codes that mean the customer has still to respond. A confirmation that the money arrived replaces an earlier failure, so a payment is never lost to one. Every order page is reached by a secret link, so tracking needs no account.

## Pages, the home page and store settings

The store's own pages (delivery, returns, guarantee, questions, contact, about, showroom, terms, privacy) are rows in `pages`, written in Markdown and served at `/<slug>`. They ship with the migrations and staff edit them under Content, Pages. HTML typed into a page is shown as text. A page that is not published opens only for signed-in staff, which is how it is previewed. The delivery page lists the fees from the delivery zones as they stand, and a page whose headings are questions tells search engines so.

The words on the home page are set under Content, Home page. How customers reach the store, the showroom's address and hours, the VAT rate and how long stock is held are set under Settings, Store. Both are kept in `settings` and laid over `config/store.php` as the application starts (`App\Support\StoreSettings`), so code reads `config('store.…')` either way. Only the keys listed in that class can be overridden.

With a WhatsApp number set, product and order pages offer a chat that opens already saying which piece or order it is about (`App\Support\WhatsApp`).

## Customer accounts

Nobody needs an account to buy. One keeps addresses for next time, lists the orders placed from it and carries the cart between devices. Customers sign in with their own guard (`customer`), separate from staff: a customer's session cannot open the back office, and signing out of the shop leaves a member of staff signed in.

- An order belongs to an account only when it was placed while signed in. Orders are never matched to an account by phone number or email, which anybody could type.
- A signed-in customer has one cart, kept with the account. Signing in pours whatever the browser was carrying into it, never past what can be bought, and signing out takes it off the device.
- Checkout starts from the account's details and usual address; each saved address is a plain link that fills the form, so choosing one needs no script.
- A forgotten password is replaced through an emailed link that works once, for an hour. The message log keeps the email with a stand-in where the link was, so nobody reading the log can use it.
- Offers by message are opt-in: the box is separate from everything else, starts unticked and can be cleared at any time under Your details.

## From paid to delivered

Staff work orders under Sales, Orders. A paid order moves one step at a time (`App\Orders\MoveOrderOn`): into the workshop if it holds a made-to-order piece, then ready, then out for delivery, then delivered. An order that is collected skips the delivery step. Each step is its own action, so a button pressed on a page that has gone stale does nothing rather than skipping ahead, and the order's history records who took it.

Cancelling (`App\Orders\CancelOrder`) needs a reason, returns the order's stock through the ledger and closes it. If the order was paid for, the history notes what is owed back; the refund itself is made by hand, and an owner records it on the order once it is sent. Money that arrives for an order already cancelled does not revive it and is noted the same way.

The back office opens on the day's figures: sales today and this month (orders counted by the day they were paid for), paid orders waiting for their next step, pieces at or below their stock warning level, and cancelled orders with money still to send back.

Customers need no account, so staff know a customer by the phone number on their orders. Sales, Customers reads from the `buyers` view, which groups orders by phone number, so it is never out of step with the orders themselves.

Delivery zones are groups of counties with one fee and a promised time, set under Settings, Delivery zones. A county belongs to one zone at a time, and a county in no zone, or in a zone that is switched off, is not offered at checkout. An order keeps the fee and the promise it was placed with.

## Messages to customers

`App\Messages\TellCustomer` writes to the customer when an order is placed, paid for, fails to be paid for (once), is being made, is ready, goes out, is delivered or is cancelled. The wording is in `App\Messages\OrderWording`.

Every message is stored in full before anything is sent, and staff read them under Sales, Messages exactly as the customer got them. An email is queued when the customer gave an address and a real mailer is configured; with the `log` mailer it is stored and marked as not sent. Because an email goes to whatever address was typed at checkout, sending is capped per address and for the whole store each day. Text messages are written and stored but not sent.

## Running it as a demonstration

With `STORE_DEMO=true` the store can be shown to people who are not its staff.

- `php artisan demo:reset` replaces the orders, customers, messages and stock movements with a fixed set: about four months of orders in every state, counted back from today, with customers at example addresses. It runs each night at 03:30, so the store starts every day the same whatever visitors did to it. It does not touch the catalogue, the pages, the settings or staff accounts that are not demonstration accounts, and it refuses to run when `STORE_DEMO` is off.
- `/demo/staff`, `/demo/staff/fulfilment` and `/demo/staff/support` sign a visitor in to a demonstration staff account of that role, and `/demo/customer` to a customer account with orders behind it. Nothing in the shop links to them, and they do not exist when `STORE_DEMO` is off.
- A demonstration staff account can open every screen its role allows and can work orders and stock, but cannot change anything customers see: products, prices, photos, pages, delivery fees, store settings or staff. `App\Demo\DemoGuard` enforces that on the models themselves, so it holds for every screen and action. The demonstration customer's name, email and password cannot be changed, so the next visitor finds the account as this one did.
- Email is never sent to an address at example.com, example.org, example.net or .test, on any installation. Such a message is kept in the log and marked as not sent.
- An M-Pesa prompt is never sent to a phone number from the demonstration data. The numbers are made up but have the shape of real ones, so one may be somebody's line: the payment page asks a visitor for their own number instead of offering the one on the order (`App\Demo\DemoData::invented`).

## Backups and alerts

`store:backup` writes a compressed SQL copy of the database to `storage/app/private/backups` every night at 02:15 and keeps a fortnight of them. With `--photos`, run weekly, it also packs the product photos and keeps the last two. The copies live in the shared storage directory, so releases come and go around them. They are on the same server as the store: copy them elsewhere as well if the server itself is what you are guarding against.

To restore, load a copy into an empty database and point the application at it:

```sh
createdb sebule_restored
gunzip -c storage/app/private/backups/database-2026-10-11-0215.sql.gz | psql sebule_restored
```

Anything the application reports as an error, a queued job that runs out of tries and a backup that fails are written to the log and, when `STORE_ALERT_EMAIL` is set, emailed to it: once in fifteen minutes for the same trouble (`App\Support\Alert`). `.github/workflows/uptime.yml` opens the live site from outside every hour and fails when a page does not load, which GitHub reports to whoever watches the repository.

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

Each release caches under its own prefix, so nothing cached by old code is read by new code. Sessions are the exception: they are kept through the `sessions` cache store, whose prefix never changes, so a deploy does not sign anybody out or lose a checkout in progress.

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
| `STORE_ALERT_EMAIL` | Where errors, failed jobs and failed backups are emailed. Without it they are only logged. |
| `STORE_DEMO` | Set to `true` only for a store that is being shown, not traded from. See "Running it as a demonstration". Off by default. |
| `STORE_TEST_PAYMENTS` | Set to `true` to let an order be completed with a test payment, with no money moving. Off by default. |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS` | How order emails leave. With `log`, emails are stored in the message log and not sent. |
| `STORE_REPLY_TO`, `STORE_PHONE`, `STORE_WHATSAPP` | How customers reach the store, until staff set them under Settings, Store. Optional. |
| `STORE_EMAIL_DAILY_LIMIT`, `STORE_EMAIL_ADDRESS_DAILY_LIMIT` | The most emails the store sends in a day, and the most to any one address. 80 and 12 by default. |
| `DARAJA_ENV` | `sandbox` or `production`. |
| `DARAJA_CONSUMER_KEY`, `DARAJA_CONSUMER_SECRET`, `DARAJA_PASSKEY`, `DARAJA_SHORTCODE` | The store's Safaricom Daraja app. Without them the M-Pesa option is not offered. |
| `MPESA_CHARGE_SHILLINGS` | A fixed number of shillings to ask for whatever the order comes to. Empty asks for the order's total. |
| `APP_RELEASE` | Overrides the release reported in the `X-Release` response header. Normally read from the `REVISION` file that the deploy writes. |
