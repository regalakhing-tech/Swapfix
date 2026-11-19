# SwapFix File Structure

| Path | Responsibility |
| --- | --- |
| `app/Application/` | Container, application assembly, view helpers, safe errors and upload coordination |
| `app/Http/` | Sessions, CSRF, role/profile guards, input parsing and security headers |
| `app/Integrations/` | Supabase Auth, Data API, Storage and PHPMailer adapters |
| `app/Modules/Accounts/` | Registration, login, onboarding and profiles |
| `app/Modules/Marketplace/` | Search, listings, updates and purchase inquiries |
| `app/Modules/Exchange/` | Trade listings, offers, counters, decisions and completion |
| `app/Modules/Repair/` | Technicians, services, quotes, approvals, tracking and reviews |
| `app/Modules/Messaging/` | Participant conversations and contextual threads |
| `app/Modules/Notifications/` | Notifications and optional SMTP outbox worker |
| `app/Modules/Activity/` | Personal listings, offers, repairs, chats and reviews |
| `app/Support/` | Crypto, decimal money, labels, UUIDs, icons and versioned assets |
| `routes/` | Browser routes and read/health APIs |
| `resources/views/` | PHP layouts, pages, partials and email templates |
| `public/` | Only web document root, HTTP entry point and optimized assets |
| `config/` | Runtime/service settings and production PHP configuration |
| `supabase/migrations/` | Eight ordered schema, business-rule and security migrations |
| `supabase/install.sql` | Generated first-install SQL Editor alternative |
| `tests/` | PHP, PostgreSQL and DOM behavior checks |
| `scripts/` | Syntax/config checks, explicit email test, image/SQL generation and deployment-input checker |
| `docs/` | Feature specification, setup, design references and assets |
| `originals/` | Retained design sources, excluded from hosting |
| `Dockerfile`, `Caddyfile`, `vercel.json` | PHP container build, routing and hosting |

Composer creates ignored `vendor/`; npm creates ignored `node_modules/` for development. Local `.env`, `.tools/`, `.vercel/` and logs are excluded from Git and hosting. Do not serve the repository root.
