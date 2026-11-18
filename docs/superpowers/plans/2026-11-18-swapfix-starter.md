# SwapFix Starter Implementation Plan

> **For agentic workers:** Use inline execution for this starter. Follow the task order and verify each integration boundary before deployment.

**Goal:** Prepare a runnable PHP project structure for the root README, with Supabase and PHPMailer adapters and Vercel container deployment files.

**Architecture:** Slim routes delegate to controllers and shared integration services. Supabase owns persistent auth, records and uploaded objects. Only `public/` is served over HTTP.

**Tech Stack:** PHP >=8.4, Composer 2, Slim 4, Guzzle 7, PHPMailer, phpdotenv, PHPUnit, Supabase, FrankenPHP, Vercel Container runtime.

## Global Constraints

- Preserve the root README's requirements and incomplete feature checklist.
- Do not infer a Supabase target from unrelated projects in the signed-in account.
- Keep credentials outside source and deployment archives.
- Never use file-based sessions or permanent local upload folders on Vercel.
- Never send a real test email without a specified test destination.
- Label module specifications separately from working starter endpoints.

## Task 1: Bootstrap and HTTP Foundation

Files: `composer.json`, `bootstrap/app.php`, `config/app.php`, `routes/web.php`, `routes/api.php`, `public/index.php`, `public/router.php`, `app/Http/*`, `app/Controllers/*`, `resources/views/*`, `public/assets/css/app.css`, `tests/HttpTest.php`.

- [x] Declare dependencies and install them with a source lockfile. The workspace is not a Git repository; no commit is created.
- [x] Write HTTP checks for `/`, `/api/health`, missing routes, and unsupported methods.
- [x] Run `composer test` and observe failure before adding the application bootstrap.
- [x] Create the Slim application with safe JSON errors for API routes and escaped PHP templates for pages.
- [x] Run `composer test` and verify the HTTP checks pass.

## Task 2: Supabase and Email Adapters

Files: `app/Integrations/Supabase/*`, `app/Integrations/Mail/*`, `app/Http/RequireAuth.php`, `config/services.php`, `.env.example`, `resources/views/emails/*`, `scripts/check.php`, `scripts/mail-test.php`, `tests/SupabaseTest.php`, `tests/MailTest.php`.

- [x] Write checks that verify publishable-key headers, user token forwarding, denial on invalid authentication, rejection of invalid uploads, and email construction without delivery.
- [x] Run `composer test` and observe adapter checks fail before implementation.
- [x] Implement adapters with HTTPS timeouts, bounded upload sizes, verified identity, and sanitized upstream failures.
- [x] Add CLI configuration and connection checks; fail clearly when credentials are absent.
- [x] Run `composer test` and verify all adapter checks pass.

## Task 3: Supabase Schema and Module Map

Files: `supabase/config.toml`, `supabase/schemas/*.sql`, `supabase/README.md`, `app/Modules/*/README.md`, `docs/FILE_STRUCTURE.md`, `docs/SETUP.md`.

- [x] Initialize Supabase using the installed CLI and discover configuration syntax.
- [x] Add starter schemas for owner-only profiles, public technician details, product listings, and durable file storage, including grants and RLS.
- [x] Document each remaining README module's pages, records and authorization requirements.
- [x] Document local setup, environment variables, schema application, Supabase Auth SMTP configuration, and PHPMailer configuration.
- [ ] Execute database checks only against a selected project; report unavailable checks accurately.

Database execution is deferred: the user confirmed starter files and supplied the local workspace path rather than a Supabase target. No hosted schema has been applied.

## Task 4: Deployment and Handoff

Files: `Dockerfile`, `Caddyfile`, `vercel.json`, `.dockerignore`, `.vercelignore`, `.gitignore`, `scripts/lint.php`, root `README.md` setup links.

- [x] Add the documented FrankenPHP container setup and keep development and secret files out of its image.
- [x] Run `composer validate --strict`, `composer audit`, `composer lint`, and `composer test`.
- [x] Serve locally using `composer dev`; request `/`, `/api/health`, CSS, a missing route, and a private file path.
- [ ] Run `vercel deploy --dry --json` and inspect the Container preset and upload inputs.
- [ ] If publishing is within scope, deploy using the signed-in account and verify the canonical landing and health routes.
- [x] Record observed checks and any missing database or SMTP configuration in the handoff.

The user confirmed project folders and starter files. Live deployment is outside this confirmed delivery. A Vercel dry run requires linking a project; no project is linked or created. Docker is unavailable locally, so the production image has not been built here. Deployment configuration follows the current official Vercel PHP/Container guide.
