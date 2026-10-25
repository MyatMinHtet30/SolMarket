# SolMarket

A Berlin student second-hand marketplace for the **HTW Berlin Solana MVP Challenge**. Find useful campus essentials, filter by neighbourhood, and give pre-loved items another semester.

**Current status:** the marketplace works locally. Product details include a checkout preview and optional Phantom wallet connection, but **payments and purchases are disabled**. Solana payments are not a working production feature. Connecting a wallet only shares its public address; it does not perform an on-chain transaction.

## Technology

- Laravel 12 and PHP 8.2+, with Blade templates and SQLite persistence.
- Tailwind CSS 4, Vite 6 and lightweight JavaScript; no frontend application framework.
- Laravel localization for English and German.
- PHPUnit/Laravel feature tests and Node's built-in JavaScript test runner.

## Working features

- Responsive green-and-cream marketplace, accessible focus states, subtle animations and reduced-motion support.
- Product listings, detail pages, pagination, search and combined category/district/condition filters with reset controls.
- Berlin districts: Friedrichshain, Kreuzberg, Mitte, Neukölln, Charlottenburg and Lichtenberg.
- Conditions: Neu, Wie neu, Gut and Gebraucht, translated when English is selected.
- EUR listing prices, seller names, Berlin locations and local pickup information.
- Add-product form with server validation and JPG, PNG or WebP uploads up to 4 MB. User-uploaded SVG files are not accepted.
- English/Deutsch language switcher; seller-written listing content remains in its original language.
- Six reproducible sample listings with bundled illustrations and public Berlin pickup landmarks.
- Product-only checkout preview with explicit Phantom Connect/Disconnect and shortened public address. Homepage and navigation have no wallet controls.

## Windows setup: fresh clone

Use PowerShell. Install Git, Composer 2, PHP 8.2 or newer, and Node.js 22.x (22.12 or newer) with npm. Put their executables on PATH. No MySQL, standalone SQLite server or global Laravel installer is required.

Check the tools:

```powershell
php -v
php --ini
php -m
composer --version
node --version
npm.cmd --version
```

PHP needs PDO SQLite, fileinfo, mbstring, XML and OpenSSL, plus Laravel's normal PHP extensions. Enable `extension=pdo_sqlite` in the CLI `php.ini` shown by `php --ini` if necessary. Composer reports missing required extensions. With XAMPP, ensure its PHP directory is on PATH and edit the CLI's actual configuration. `storage` and `bootstrap/cache` must be writable.

Clone your submitted repository, replacing the placeholder URL:

```powershell
git clone https://github.com/YOUR_USERNAME/SolMarket.git
Set-Location SolMarket
composer install
Copy-Item .env.example .env
php artisan key:generate

# Create SQLite only if absent: never truncate an existing database.
if (-not (Test-Path -LiteralPath 'database/database.sqlite')) {
    New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null
}

php artisan migrate --seed
php artisan storage:link
npm.cmd ci
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open **http://127.0.0.1:8000**. Keep the server terminal open; Ctrl+C stops it. The built assets work without a running Vite development server. `npm.cmd` avoids PowerShell's possible restriction on `npm.ps1`.

The default SQLite path is `database/database.sqlite`; no database username or password is needed. Database migrations also create Laravel session/cache/queue tables and the retained historical demo-order schema. Seeding supplies sample products, not payment records. No wallet, RPC credentials, private keys, recovery phrases or SOL balance are needed to browse, add products or run tests.

For frontend development, optionally run `npm.cmd run dev` in a second terminal while `php artisan serve` runs. Before sharing a built demo, stop Vite and run `npm.cmd run build` again.

### Existing installations

Keep your existing `.env`, `APP_KEY`, SQLite database and `storage/app/public` files. Do not repeat the fresh-clone environment/key creation steps, use `migrate:fresh`, delete the database, or roll back historical payment tables. An existing app key may be needed to read retained encrypted historical data. Back up local data before any future schema changes and review migrations before applying them.

To rebuild an already configured installation:

```powershell
composer install
npm.cmd ci
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

## Sample products and images

`database/seeders/DatabaseSeeder.php` provides a backpack, calculus textbook, desk lamp, headphones, coffee set and bicycle. Sellers are fictional examples; pickup locations are public Berlin landmarks defined in `app/Support/BerlinSamples.php`, not private home addresses. The seeder is repeatable without duplicating the six samples. It preserves edited sample values, filling only missing location/condition/pickup metadata. Run it on a fresh demo database with `php artisan migrate --seed`; do not reseed your personal database just to prepare a submission.

The six original SVG illustrations in **`public/samples/` must be committed**. They work immediately after cloning and do not depend on user uploads, an external image host or a storage symlink.

Newly uploaded photos live in `storage/app/public/products`. `php artisan storage:link` exposes this directory through `public/storage`. If Windows denies symlink creation, enable Windows Developer Mode or run that one command in a PowerShell terminal with the required privileges. Neither uploaded photos nor the generated link belongs in Git. A fresh clone intentionally does not contain existing users' photos or listings; create a new listing locally to demonstrate uploads. If uploads fail, check PHP's `upload_max_filesize` (at least `4M`) and `post_max_size` (for example `8M`) and restart the PHP server.

## Verification

```powershell
php artisan test
npm.cmd test
npm.cmd run build
```

Laravel tests use in-memory SQLite and fake upload storage, not the local marketplace database. They cover repeatable seeding, browse/search/filter combinations, pagination, product creation and image validation, localization, checkout disclosures and disabled payment safeguards. JavaScript tests use mocked wallets and cover filters, connection/disconnection, rejected requests and the blocked checkout. These tests do not send real transactions. Historical payment tests under `tests/Archive/` are reference material outside the active suites.

For a manual presentation check:

1. Browse the six sample products and switch between English and Deutsch.
2. Search for `lamp`, combine Furniture and Lichtenberg, then reset filters.
3. Open a product and check EUR price, condition, district and pickup information.
4. Add a listing with a local photo, then reload to confirm persistence.
5. Check the mobile layout. Optionally open Buy Now on a detail page and explicitly connect Phantom in Chrome; missing-extension and rejected-request states are handled. Continue to Payment must remain disabled after connecting. Connecting does not pay for an item.

## Payment status and limitations

The checkout preview is **not an order fulfilment or EUR purchase flow**. Its expandable checkout confirmation explains that a future demo transaction would use **0.001 Devnet test SOL**, with no real-money value, and would not purchase the EUR-listed item. This fixed demonstration amount is not a conversion of the listing price.

Transfers remain disabled because a previously reported recipient mismatch is unresolved. Payment endpoints are unregistered, backend guards remain locked, and the current checkout does not load a transaction-sending module. A blank optional public-recipient field in `.env.example` does not enable payments. Existing local recipient settings and historical records are preserved, not published.

Before any future transfer can be enabled, the recipient review must be resolved with verifiable transaction evidence; displayed configuration, pending-order recipient and transaction destination must agree. Explicit disclosure/approval, Devnet-only enforcement, successful backend confirmation, payer/recipient/amount validation and duplicate-signature protection must remain mandatory. See [recipient review](docs/checkout-recipient-review.md) and [retained payment code](archive/solana-payments/README.md). There is no Mainnet payment capability or production payment claim.

This university prototype has no user accounts, verified sellers, messaging, moderation, editing/deleting listings or completed purchase workflow. Listings publish immediately; seller names are entered by their creators. Wallet connection does not authenticate marketplace ownership. The local PHP development server is for local use, not production hosting.

Future work includes accounts and listing management, safer seller/contact workflows, moderation, improved image handling and accessibility testing. Any Solana Devnet demonstration or other on-chain integration is future work requiring explicit review and end-to-end verification before activation.

## Repository contents and privacy

Commit application source, migrations, seeders, tests, translations, bundled sample illustrations, `.env.example`, `composer.lock` and `package-lock.json`. Install dependencies from the lockfiles; do not commit `vendor` or `node_modules`.

Git exclusions cover local `.env` variants (except `.env.example`), SQLite databases and journals, local database exports/backups, uploaded public/private storage, logs/caches, generated builds, common key files and local credential files. Never put secrets into source code, README examples, remote URLs or screenshots. Review staged files before publishing; `.gitignore` cannot remove secrets from an existing Git history. Keep any local database/image backups outside the repository.

Useful source locations:

- `routes/web.php`, `app/Http/Controllers/`, `app/Models/`: marketplace routes and backend.
- `database/migrations/`, `database/seeders/`: reproducible schema and safe sample data.
- `resources/views/`, `resources/css/`, `resources/js/`: Blade interface and lightweight assets.
- `lang/`: English/German localization.
- `public/samples/`: bundled demonstration images.
- `tests/Feature/`, `tests/Unit/`, `tests/js/`: active automated tests.

## GitHub submission commands

Create an empty GitHub repository first, without an auto-generated README. The following commands are instructions only; no repository initialization, commit, deployment or push is performed automatically. Replace `YOUR_USERNAME` with your account. Use Git's credential manager for authentication; never put a token into the remote URL.

```powershell
git init
git branch -M main
git add .
git status --short
git diff --cached --stat
git diff --cached --name-only
# Review staged files: no .env, private databases, user uploads or credentials.
git commit -m "Prepare SolMarket challenge submission"
git remote add origin https://github.com/YOUR_USERNAME/SolMarket.git
# Only run after approving publication:
git push -u origin main
```

If the project is already a Git repository, skip `git init` and inspect `git remote -v` before adding or changing a remote. Publishing is a separate, explicit step.
