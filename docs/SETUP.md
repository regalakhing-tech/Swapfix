# SwapFix Setup and Deployment

## Current Capabilities

The starter runs a redesigned PHP public page, a server-rendered sample catalog, and a JSON health route. Preview browsing supports three sections, search, category filters, price sorting and detail dialogs, with browser-local saved samples when JavaScript is enabled. It provides tested integration adapters for Supabase Auth, Data API access, image uploads, and PHPMailer. It also includes starter SQL and module guides for the full application.

Live marketplace publishing, account forms, exchange transactions, repair workflows, chat, notifications and reviews are specified in the README and module guides; their business endpoints are not implemented by this starter. The public catalog contains clearly labeled sample records. Database SQL is prepared separately from PHP tests. Configured environment variables alone do not prove database connectivity or email delivery.

See [UI assets](UI-ASSETS.md) for the transparent logo, generated phone-and-gadget image, user-supplied photos and optional DOM checks. The finished PHP site needs no Node build. Styles, JavaScript and the font use versioned URLs; image filenames contain content hashes. Public HTML uses `no-cache`, private/API responses keep `no-store`, and assets are cached independently.

## Requirements

- PHP 8.4 or newer, with cURL, Fileinfo, Mbstring and OpenSSL.
- Composer 2.
- A selected Supabase project and its publishable key for connected features.
- SMTP credentials for application email.
- A Vercel account for deployment.
- Docker only if you want to build or preview the production container locally.

## Run Locally on Windows

From the project directory:

```powershell
composer install
Copy-Item .env.example .env
composer dev
```

If `.env` already exists, edit it instead of copying over it. Visit `http://127.0.0.1:8000`. The runtime check is `http://127.0.0.1:8000/api/health`. Stop the development server with Ctrl+C.

Always serve `public/` as the document root. For XAMPP or Apache, configure a virtual host pointing to that folder and route non-file requests to `index.php`; do not expose the workspace directory.

## Environment Variables

| Variable | Purpose |
| --- | --- |
| `APP_NAME` | Application name; defaults to SwapFix |
| `APP_ENV` | `local`, `testing`, or `production` |
| `APP_URL` | The actual site origin, including HTTPS on Vercel |
| `APP_TIMEZONE` | Application timezone; defaults to Asia/Manila |
| `SUPABASE_URL` | The selected project's HTTPS API URL |
| `SUPABASE_PUBLISHABLE_KEY` | A current `sb_publishable_...` API key |
| `UPLOAD_MAX_BYTES` | Image size limit from 1 through 5242880 bytes |
| `SMTP_HOST` | Your SMTP provider's hostname |
| `SMTP_PORT` | Usually 587 for STARTTLS or 465 for implicit TLS |
| `SMTP_ENCRYPTION` | `tls` for STARTTLS, or `ssl` for implicit TLS |
| `SMTP_USERNAME` | SMTP authentication username, if required |
| `SMTP_PASSWORD` | SMTP password or provider-issued application password |
| `MAIL_FROM_ADDRESS` | Verified sender address |
| `MAIL_FROM_NAME` | Displayed sender name |

Local `.env` values are loaded by phpdotenv. Hosted process variables take precedence. Set actual values in Vercel's Environment Variables settings rather than uploading `.env`.

The adapters deliberately use a publishable key plus the user's access token. A secret or service-role key is not needed for this starter and must not replace the publishable key. See [Supabase API keys](https://supabase.com/docs/guides/getting-started/api-keys).

## Connect Supabase

1. Choose or create a dedicated project in your own Supabase account.
2. Follow [the schema and storage guide](../supabase/README.md) against that selected project.
3. Put its URL and publishable key in local `.env` and the matching Vercel environment.
4. Run `composer check:supabase`. A successful read verifies the PHP application's Data API access to `product_listings`; it does not verify every future business workflow.
5. When building account forms, configure Supabase Auth's Site URL and allowed redirects for the real local and hosted origins. Choose a secure cookie or frontend session approach, including token refresh and CSRF protection when cookies are used, before exposing those forms.

Account types are stored in `profiles.account_type`, whose update privileges are restricted. Do not authorize technician operations using user-editable Auth metadata.

## Use the Supabase Adapters

```php
use SwapFix\Integrations\Supabase\SupabaseClient;
use SwapFix\Integrations\Supabase\Auth;
use SwapFix\Integrations\Supabase\Storage;

$services = require dirname(__DIR__) . '/config/services.php';
$settings = $services['supabase'];
$client = new SupabaseClient($settings['url'], $settings['publishable_key']);
$auth = new Auth($client);

// $accessToken is the signed-in user's token, supplied by the authenticated request.
$user = $auth->user($accessToken);
$listings = $client->request('GET', '/rest/v1/product_listings', [
    'query' => ['select' => 'id,title,price,location,status', 'limit' => 20],
], $accessToken);

$storage = new Storage($client, $auth, $settings['upload_max_bytes']);
// A controller must first check UPLOAD_ERR_OK and is_uploaded_file().
$uploaded = $storage->upload($accessToken, $temporaryUploadPath, 'listing-images');
```

Available auth methods are `signUp(email, password)`, `signIn(email, password)`, `user(accessToken)`, `refresh(refreshToken)` and `signOut(accessToken)`. Account confirmation may be required before login; a signup response is not always an authenticated session.

The upload adapter validates image content, checks size, verifies the user with Auth, and generates a random filename inside that user's UUID folder. Its supported buckets are `avatars`, `listing-images` and `repair-images`. The starter's private repair bucket grants access only to its owner; technician access requires participant policies when the Repair module is implemented.

## Prepare PHPMailer

PHPMailer is installed by Composer and wrapped in `app/Integrations/Mail/Mailer.php`. It supports encrypted SMTP and multipart HTML/plain-text messages. Delivery credentials must be provided by your SMTP provider; use a verified sender.

After configuring SMTP, send a test only to an address you control:

```powershell
php scripts/mail-test.php your-test-address@example.com
```

That command sends a real email. A successful result means the SMTP server accepted the message; check the inbox or capture service for actual delivery. The PHPUnit mail test only constructs the message and never sends it.

Supabase Auth confirmation and password-reset emails use **Supabase Auth's custom SMTP settings**, configured separately in the Supabase Dashboard. Installing PHPMailer does not configure Auth's mail provider. See [Supabase custom SMTP](https://supabase.com/docs/guides/auth/auth-smtp).

Application services can use `Mailer::prepare()` to construct a message or `Mailer::send()` to send one after a verified business event. Use the actual participant's address from a trusted record. Do not expose an unauthenticated endpoint accepting arbitrary recipients. A repair-update template is available in `resources/views/emails/repair-update.php`.

## Verification Commands

```powershell
composer validate --strict
composer audit
composer lint
composer test
composer check
composer check:supabase
```

`composer check` reports configuration presence without printing values. `composer check:supabase` performs a real read and exits unsuccessfully when Supabase is missing or inaccessible. `/api/health` checks the running PHP application and reports `not_checked` for external connectivity and SMTP delivery.

## Deploy to Vercel

The root `Dockerfile` packages PHP and production Composer dependencies with FrankenPHP. `Caddyfile` serves `/app/public` and listens on `PORT`, defaulting to 80. `vercel.json` selects the Container preset. This follows [Vercel's PHP deployment guide](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker) and [Container runtime documentation](https://vercel.com/docs/functions/runtimes/container).

From the project root:

```powershell
vercel login
vercel link --project swapfix
vercel deploy --dry --json
vercel deploy --prod
```

Check the selected account/team and project when linking. Create a new project if you do not already have the intended SwapFix project. Inspect the dry-run output for the Container preset and upload files. The ignore rules exclude `.env` files, tests, vendor packages, scripts and local metadata; dependencies are installed from the lockfile inside the build.

Add the environment variables to the selected Vercel project for Production and any Preview environments you intend to use. Set `APP_ENV=production` and `APP_URL` to the actual canonical HTTPS address. Redeploy after changing variables. Add the same origin to Supabase Auth settings when account flows are built.

After deployment, check the canonical address, `/assets/css/app.css`, `/api/health`, an unknown route, and a private path such as `/.env`. The private path must not return file contents. Runtime health can succeed before Supabase and SMTP are configured.

Vercel instances do not provide durable application files or file sessions. Use Supabase Storage for images and Supabase for persistent records. For local container testing, install Docker and run:

```powershell
docker build -t swapfix .
docker run --rm -p 8080:80 --env-file .env swapfix
```

Open `http://127.0.0.1:8080`. `.env` is passed at runtime and is excluded from the image.
