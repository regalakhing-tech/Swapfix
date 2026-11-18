# SwapFix Setup and Deployment

## Application

PHP 8.4+, Composer 2, cURL, Fileinfo, Mbstring and OpenSSL are required. Node is used only for development checks and optional image optimization. Serving the website requires no Node build.

| Area | Routes |
| --- | --- |
| Illustrated homepage | `/` |
| Accounts | `/register`, `/login`, `/onboarding`, `/account` |
| Marketplace | `/market`, `/market/new`, `/market/{id}` |
| Exchanges | `/exchange`, `/exchange/new`, `/exchange/{id}`, `/offers/{id}` |
| Repairs | `/repair`, `/technicians/{id}`, `/repair/request/{id}`, `/repairs` |
| Technician workspace | `/technician/dashboard`, `/technician/services`, `/technician/profile` |
| Messages and records | `/messages`, `/notifications`, `/activity` |

Supabase Auth manages credentials. Encrypted HttpOnly cookies hold session tokens; browser writes require CSRF tokens and origin checks. PostgreSQL validates the actor, ownership, approvals and current state for each mutation. Images are stored in Supabase Storage.

## Local setup

Run `composer install`. For a new checkout, copy `.env.example` to `.env`; preserve any existing environment file. Generate a unique `APP_KEY` per environment with:

```powershell
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Set the selected project's `SUPABASE_URL` and `SUPABASE_PUBLISHABLE_KEY`. Store secrets only in ignored local configuration or the hosting secret store. Run `composer dev` and open `http://127.0.0.1:8000`. Always serve only `public/`, never the repository root. See [database setup](../supabase/README.md).

## Email

PHPMailer supports encrypted SMTP and HTML/plain-text messages. Set the SMTP and sender variables from `.env.example` using your provider's verified sender. In-app notifications work without SMTP.

Optional notification email needs SMTP, a server-only `SUPABASE_SECRET_KEY` and `CRON_SECRET`. The worker endpoint is `/internal/mail/dispatch`; schedule it only after configuration. It sends queued notifications to opted-in participants. Its template is `resources/views/emails/notification.php`.

Supabase Auth confirmation emails have a separate mail configuration. Set Auth's production site URL and allow the canonical `/login?confirmed=1` redirect. Keep email confirmation enabled. See [Supabase custom SMTP](https://supabase.com/docs/guides/auth/auth-smtp).

The explicit command `php scripts/mail-test.php your-test-address@example.com` sends a real test email. SMTP acceptance and inbox delivery are separate checks. Automated tests never send email.

## Checks

```powershell
composer validate --strict
composer audit
composer lint
composer test
composer check:supabase
npm ci
npm run test:database
# With composer dev running:
npm run test:ui
```

PHP tests cover routing, escaping, account guards, session-cookie precedence, CSRF, integration adapters and mail construction. PGlite executes the actual migrations with local Auth/Storage stubs to test permissions, approvals, state transitions and records. It is not a hosted integration test or a multi-session concurrency test. DOM checks exercise the shipped scripts without browser automation or visual/Core Web Vitals measurements.

`composer check:supabase` performs a real Data API read. `/api/health` reports runtime/configuration state and does not claim it checked external connectivity or email delivery.

## Vercel

The project is `swapfix` in scope `khing-jay`, with canonical URL `https://swapfix.vercel.app`. The Dockerfile uses FrankenPHP, production Composer dependencies and an authoritative class map. Caddy serves `/app/public`, compresses responses and caches versioned assets separately from private HTML.

Configure `APP_ENV=production`, the canonical HTTPS `APP_URL`, a unique production `APP_KEY`, `APP_TIMEZONE`, `SUPABASE_URL` and `SUPABASE_PUBLISHABLE_KEY` in Vercel. SMTP/worker settings are optional. Never expose a privileged Supabase key to clients.

```powershell
vercel deploy --dry --json --scope khing-jay > .tools/vercel-dry.json
node scripts/check-deployment.mjs
vercel deploy --prod --scope khing-jay
```

The upload checker ensures every runtime file is present and private configuration is excluded. Root-anchored ignore rules are important on Windows: excluding an unanchored `supabase` directory also excluded `app/Integrations/Supabase`.

After publishing, verify the homepage, actual search routes, assets, sign-in guards and private-file 404s. A build alone does not establish a working app. Records, uploads and queues remain durable in Supabase.

The deployment follows [Vercel's PHP guide](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker) and [Container runtime](https://vercel.com/docs/functions/runtimes/container), which is currently beta.
