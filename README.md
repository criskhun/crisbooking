# Davao Rent Zone

Davao Rent Zone is a Laravel application for car rentals, condo rentals, driving services, and pet transportation. It includes user registration, email verification, Google and Facebook login, listings, availability calendars, bookings, inquiries, and account roles.

## Requirements

- PHP 8.4.1 or newer
- Composer 2
- MySQL/MariaDB for production (SQLite is supported for local development and tests)
- Apache with `mod_rewrite` or an equivalent web server

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

The local site is available at `http://127.0.0.1:8000`.

## Deploying to Hostinger

### 0. Choose a PHP website, not a Node.js/Web App deployment

Davao Rent Zone is a server-rendered Laravel/PHP application. In hPanel, create or use a **custom PHP website**, then deploy the repository from **Dashboard -> Advanced -> Git** into `public_html`.

Do **not** use Hostinger's **Deploy Web App**, **Node.js**, **Vite**, or static frontend workflow. If Hostinger asks for an output directory such as `dist`, the repository has been connected through the wrong deployment type. Laravel Vite generates optional frontend assets in `public/build`, but neither `dist` nor `public/build` contains the PHP application, routes, authentication, or database logic. Changing `vite.config.js` to output to `dist` will not deploy Davao Rent Zone.

### 1. Configure PHP and the domain

In hPanel, set the website to PHP 8.4. The locked Symfony dependencies require PHP 8.4.1 or newer, so the website runtime and SSH deployment command must both use PHP 8.4. On Hostinger Web and Cloud hosting, the document root is normally fixed at `public_html`. Deploy this entire repository into `public_html`; the root `.htaccess` included here safely forwards requests to Laravel's `public/` directory.

On a VPS, configure Apache or Nginx so the document root points directly to `PROJECT/public`, which is Laravel's preferred layout. Do not move Laravel's `index.php` into the project root and do not expose `.env`.

### 2. Create the production environment

Create a MySQL database and user in **hPanel -> Databases -> Management**. Save the database name, username, password, and host shown by Hostinger. Through SSH or Hostinger's file manager, copy the production sample:

```bash
cp .env.hostinger.example .env
```

Edit `.env` and replace these placeholders with the exact database values from hPanel:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_hostinger_database_name
DB_USERNAME=your_hostinger_database_user
DB_PASSWORD=your_hostinger_database_password
```

The database can be empty. The deployment script runs `php artisan migrate --force` to create all Davao Rent Zone tables. Do not import the local SQLite file.

Important production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://davaorentzone.com
DB_CONNECTION=mysql
SESSION_SECURE_COOKIE=true
```

Keep `.env` only on the server. It is ignored by Git and must never contain credentials in the repository. Back it up before changing the Git deployment target, because Hostinger can overwrite files in that target directory. The deployment script generates `APP_KEY` only when it is empty; changing an existing key would invalidate encrypted data and active sessions.

### 3. Install and deploy

From the project directory on Hostinger:

```bash
chmod +x scripts/deploy-hostinger.sh
./scripts/deploy-hostinger.sh
```

Hostinger Web/Cloud plans normally provide Composer as `composer2`. If SSH uses a different PHP version from the website, run the script with Hostinger's PHP binary, for example. The script uses this binary for both Composer and Artisan:

```bash
PHP_BIN=/opt/alt/php84/usr/bin/php ./scripts/deploy-hostinger.sh
```

The script installs production dependencies, checks the PHP version and environment, makes Laravel's runtime folders writable, runs migrations, creates the public storage link, and caches configuration, routes, and views.

Do not run `php artisan db:seed` in production: the current development seeder creates `test@example.com`.

### 4. Configure the Hostinger business email

In **hPanel -> Emails**, create or open the mailbox that will send Davao Rent Zone messages (for example, `bookings@davaorentzone.com`). Use the mailbox password—not your Hostinger account password—in the server's `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=bookings@davaorentzone.com
MAIL_PASSWORD=your_mailbox_password
MAIL_TIMEOUT=15
MAIL_FROM_ADDRESS=bookings@davaorentzone.com
MAIL_FROM_NAME="${APP_NAME}"
NOTIFICATION_EMAIL_INACTIVE_MINUTES=5
```

This mailbox sends email verification and password-reset links. It also receives a copy of each in-app alert when its recipient has not used the site for the configured number of minutes. Never commit the mailbox password.

After changing these values, rebuild Laravel's configuration cache with the same PHP 8.4 binary used for deployment:

```bash
PHP_BIN=/opt/alt/php84/usr/bin/php
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
```

Then open `/forgot-password`, enter a real account email, and confirm that the reset email arrives. If it does not, inspect the most recent mail error:

```bash
tail -n 100 storage/logs/laravel.log
```

Verify the mailbox credentials in **hPanel -> Emails -> Manage -> Connect Apps & Devices**, and make sure `APP_URL` uses the live HTTPS domain so links in the email point to the public site. Hostinger's primary outgoing configuration is SSL on port 465. If the log reports an encryption or connection problem, try Hostinger's fallback:

```env
MAIL_SCHEME=smtp
MAIL_PORT=587
```

Then clear and rebuild the configuration cache again. An incorrect mailbox username/password usually appears in the log as an SMTP authentication failure.

### 5. Configure Google and Facebook login

Because the public site uses HTTPS, register these exact callback URLs with the providers:

```text
https://davaorentzone.com/auth/google/callback
https://davaorentzone.com/auth/facebook/callback
```

Then add the client IDs and secrets only to the server `.env`. For Facebook, also set the app's website URL and App Domain to the production domain and switch the Meta app to Live when public testers need access. While the app remains in Development mode, only app roles/testers can sign in.

After any `.env` change, refresh the cached configuration:

```bash
php artisan optimize:clear
php artisan config:cache
```

### 6. Verify the deployment

Open these URLs:

- `https://davaorentzone.com/up` should return HTTP 200.
- `https://davaorentzone.com/register` should show registration.
- `https://davaorentzone.com/forgot-password` should send a reset link through the Hostinger mailbox.
- Google and Facebook login should return to the HTTPS domain, not localhost.
- Upload a listing photo to confirm `public/storage` is available.

If the site returns HTTP 500, inspect `storage/logs/laravel.log`. The most frequent causes are an unsupported CLI PHP version, missing `.env`/`APP_KEY`, incorrect MySQL credentials, migrations not run, or non-writable `storage` and `bootstrap/cache` directories.

## Google Maps

Enable the Maps JavaScript API in Google Cloud, create a browser key, and set:

```env
GOOGLE_MAPS_API_KEY=your_browser_api_key
GOOGLE_MAPS_MAP_ID=
```

Restrict the key to the production domain's HTTP referrers. `GOOGLE_MAPS_MAP_ID` is optional. Without a key, text location search still works and the interface displays a setup notice instead of a map.

## Tests

```bash
composer test
```
