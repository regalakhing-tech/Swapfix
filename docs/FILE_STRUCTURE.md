# SwapFix File Structure

The starter separates public files, PHP application code, integration configuration, templates, database preparation, tests and documentation. It does not expose the project root as a website directory.

```text
Subebe_Final_WS102/
├── app/
│   ├── Controllers/
│   │   ├── HomeController.php          # Starter page
│   │   └── HealthController.php        # Runtime and configuration status
│   ├── Http/
│   │   ├── Json.php                    # JSON responses
│   │   ├── RequireAuth.php             # Verified Supabase bearer identity
│   │   └── SecurityHeaders.php         # Shared HTTP headers
│   ├── Integrations/
│   │   ├── Mail/Mailer.php             # PHPMailer SMTP adapter
│   │   └── Supabase/
│   │       ├── SupabaseClient.php      # HTTPS Auth, Data API and Storage access
│   │       ├── Auth.php                # Sign up/in, verify, refresh and sign out
│   │       └── Storage.php             # Validated user-owned image uploads
│   ├── Modules/
│   │   ├── Accounts/README.md
│   │   ├── Marketplace/README.md
│   │   ├── Exchange/README.md
│   │   ├── Repair/README.md
│   │   ├── Messaging/README.md
│   │   ├── Notifications/README.md
│   │   ├── Reviews/README.md
│   │   └── Activity/README.md
│   └── Support/
│       ├── Env.php                     # Process/local configuration lookup
│       ├── Assets.php                  # Versioned assets and responsive image manifest
│       ├── Icon.php                    # Shared inline SVG icons
│       └── IntegrationException.php    # Safe integration failures
├── bootstrap/app.php                   # Autoload, environment, routes and errors
├── config/
│   ├── app.php                         # App name, origin, environment and timezone
│   └── services.php                    # Supabase, upload and SMTP configuration
├── public/                             # The ONLY web document root
│   ├── index.php                       # HTTP entry point
│   ├── router.php                      # Local PHP development router
│   └── assets/css/app.css              # Starter page styles
├── resources/views/
│   ├── home.php                        # PHP page template
│   └── emails/repair-update.php         # Escaped repair email template
├── routes/
│   ├── web.php                         # Browser routes
│   └── api.php                         # API routes
├── scripts/
│   ├── check.php                       # Configuration / optional Data API check
│   ├── lint.php                        # PHP syntax verification
│   └── mail-test.php                   # Explicit CLI-only SMTP delivery test
├── supabase/
│   ├── config.toml                     # Local Supabase configuration
│   ├── schemas/
│   │   ├── 01_profiles.sql             # Private accounts and ownership policies
│   │   ├── 02_technicians.sql          # Public technician details
│   │   └── 03_product_listings.sql     # Marketplace records and ownership policies
│   ├── setup/storage.sql               # Buckets and object access policies
│   └── README.md                       # Database setup and verification guide
├── tests/                              # HTTP, auth, Supabase and mail checks
├── docs/                               # Setup, structure and implementation notes
├── .env.example                        # Sanitized configuration template
├── .gitignore                          # Local secrets and generated files
├── .dockerignore                       # Container context exclusions
├── .vercelignore                       # Deployment source exclusions
├── Caddyfile                           # FrankenPHP public root and HTTP listener
├── Dockerfile                          # Production PHP image and dependencies
├── composer.json                       # PHP dependencies and commands
├── composer.lock                       # Pinned dependency versions
├── phpunit.xml                         # Test configuration
├── vercel.json                         # Vercel Container framework selection
└── README.md                           # Complete SwapFix feature specification
```

The UI also adds `app/Preview/Catalog.php`, illustrative records in `resources/data/catalog.php`, the generated `resources/data/images.json` manifest, a shared card partial under `resources/views/partials/`, and self-hosted files in `public/assets/images/`, `public/assets/fonts/`, and `public/assets/js/`. `originals/` retains design image sources outside the public directory and deployment image. These preview records do not modify Supabase.

Composer generates `vendor/`. Supabase generates local metadata under its ignored internal directories. Vercel creates `.vercel/` after linking. `.env` is local and ignored.

## Where to Add Features

Each `app/Modules/<Name>/README.md` maps a part of the root requirements to its intended pages, routes, records and authorization rules. These module guides describe work to build; they are not working business endpoints.

Keep feature-specific controllers and application services under their module. Register their routes in `routes/web.php` or `routes/api.php`. Place browser templates under `resources/views/<module>/`, mail templates under `resources/views/emails/`, and frontend assets under `public/assets/`.

## Request Flow

```text
Browser -> public/index.php -> bootstrap/app.php -> routes
        -> middleware -> controller -> application/integration service
        -> Supabase or SMTP -> HTML/JSON response
```

For protected routes, add `RequireAuth` and read `user` and `access_token` from request attributes. The middleware verifies the token with Supabase before the route runs. Always pass that same access token to user-scoped Data API operations. Do not trust a submitted owner ID or a decoded, unverified JWT.

Uploads go to Supabase Storage. Temporary PHP upload files are used only while forwarding the request. Supabase user sessions supply identity; this starter does not use PHP file sessions.
