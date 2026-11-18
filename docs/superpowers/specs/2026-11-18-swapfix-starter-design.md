# SwapFix PHP Starter Design

## Scope

Prepare an organized, runnable project foundation for the SwapFix requirements in the root README. The requested technologies are PHP for the backend, Supabase for authentication, PostgreSQL data and durable uploads, PHPMailer for application email, and Vercel for deployment. This delivery prepares the foundation and module boundaries; the full feature checklist remains the implementation roadmap.

## Architecture

Use PHP 8.4 or newer, Composer autoloading, Slim 4 for HTTP routing, and PHP templates with static assets in `public/`. Keep application code, configuration, email templates, and credentials outside the document root. Route requests through `public/index.php` and use the same bootstrap for local and hosted execution.

The alternatives are a larger Laravel application, which adds conventions and infrastructure beyond this starter, or a custom router, which adds avoidable maintenance. A small Slim application provides an established request pipeline while preserving a straightforward PHP folder structure.

## Integrations

- Access Supabase over HTTPS with Guzzle and the publishable API key. Forward a signed-in user's access token for authenticated operations so Row Level Security continues to apply.
- Verify access tokens through Supabase Auth before identifying a user. Do not use a server secret to bypass user authorization.
- Store uploads in Supabase Storage with user-owned paths, MIME and size validation, and private repair-photo storage. No persistent uploads or sessions are stored on a Vercel instance.
- Install PHPMailer with Composer. Configure SMTP through environment variables. Provide a CLI-only mail-check command and no public arbitrary-recipient email endpoint.
- Use Supabase Auth's own SMTP configuration for account confirmation and password reset messages. PHPMailer handles application email independently.

## Module Boundaries

Provide module folders for Accounts, Marketplace, Exchange, Repair, Messaging, Notifications, Reviews, and Activity. Each documents the README features, intended routes, and remaining implementation. Keep integration services shared rather than duplicating credentials or HTTP clients in each module.

## Database Preparation

Provide declarative starter schemas for profiles, public technician profiles, product listings, and storage policies. Use UUID identifiers, exact numeric prices, timestamp-with-time-zone fields, indexes on ownership and browse filters, explicit role grants, and RLS. Keep private profile data owner-only. Use separate public technician fields so account information is not exposed.

The remaining exchange, quotation, repair, messaging and review records are documented in the module map. Their state-transition and participant-access rules must be implemented together with their corresponding features rather than exposing unfinished write policies.

## Deployment and Configuration

Use Vercel's documented Container runtime with FrankenPHP, a root Dockerfile, a Caddyfile pointing at `/app/public`, and a deployment ignore list. Local development also works with PHP's built-in server, without Docker. Exclude `.env`, `.env.*` secrets, tests, development tools and local runtime files from deployment; retain `.env.example` in source.

Use process environment variables on Vercel and an ignored root `.env` locally. Never print keys, passwords, tokens or raw upstream responses in public errors. Missing integration credentials produce clear configuration errors rather than fabricated success.

## Verification

Test health and unknown routes, error handling, Supabase key/token forwarding, authentication failures, upload rejection, and PHPMailer message construction without sending real email. Run Composer validation, a dependency audit, PHP syntax checks and local HTTP checks. Inspect Vercel's selected runtime and upload file list before deployment. Verify hosted routes if deployment succeeds. Database connectivity and SMTP delivery are separate checks and require a selected Supabase project and valid SMTP settings.
