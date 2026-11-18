# SwapFix Database

The selected hosted project is **kiyozen28's Project**, reference `tndhhskcfhqywvxedpso`, endpoint `https://tndhhskcfhqywvxedpso.supabase.co`. Other projects visible to an unrelated CLI account are not this destination.

## Schema and access

Eight ordered migrations create 18 application tables covering profiles and private settings; technicians/services; product listings; exchanges/offers/immutable terms/events; repair requests/quotations/added costs/history; contextual conversations/messages; notifications/email outbox; and reviews.

Every application table has RLS enabled. Exposed tables receive SELECT grants only; guarded RPCs validate mutations. Definer functions live in the unexposed `swapfix_private` schema with a fixed search path. Actor IDs come from verified Auth identity.

The `avatars` and `listing-images` buckets are public. `repair-images` and `offer-images` are private and shared with relevant participants through authorized signed URLs. Files use random names inside the uploader's UUID folder.

## First installation

Choose one path for a fresh compatible project:

1. Apply `migrations/*.sql` in order through the Supabase CLI after authenticating to the correct account and linking the inspected project; or
2. Copy all of **install.sql** into a new SQL Editor query in the selected project and run it once.

Generate the installer with `node scripts/build-database-install.mjs`. It wraps all migrations in a transaction, aborts if SwapFix/profile objects or conflicting buckets already exist, preserves unrelated tables, reloads the API schema and lists the 18 protected tables. Do not rerun it to refresh a dashboard.

After installation, Table Editor shows 18 SwapFix tables plus pre-existing tables. Empty rows are normal before real users create records. Preserve the unrelated `game_inputs` and `plain_text_submissions` tables.

**Unsaved edits** concerns SQL query text. Save the query before reloading if you want to retain its text; it does not undo committed database changes. A stale table sidebar can be refreshed after installation.

SQL Editor does not register CLI migration history. Before using CLI migrations for subsequent changes, compare the installed schema and reconcile history using the current CLI's migration-repair workflow. Do not push the initial migrations blindly over an installed project.

## Application connection

Set this project's URL and publishable key in ignored local `.env` and Vercel environment variables. Normal requests use the Data API with the current user's access token. They need no database password or privileged service key.

`composer check:supabase` performs a real Data API read. Browse `/market`, `/exchange` and `/repair` to exercise public search RPCs. Use distinct customer and technician accounts for authenticated workflow and isolation checks. Configure Auth's site/confirmation redirect URLs for the actual production origin. Auth's SMTP provider is separate from PHPMailer.

Run `npm ci` and `npm run test:database` for local PostgreSQL/PGlite checks, including installer preservation, RLS and representative allowed/denied actions. A single-session harness cannot prove multi-session review aggregate locking.
