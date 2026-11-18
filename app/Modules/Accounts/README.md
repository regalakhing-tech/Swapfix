# Accounts Module

Status: module specification. Shared Auth and authentication-middleware adapters exist; account forms and business endpoints are not registered yet.

## README Features

- Customer and technician registration, login and logout.
- Name, location and profile-photo management.
- Public technician specialties, brands, service area, hours and reviews.

## Intended Pages and API

Pages: `/register`, `/login`, `/profile`, `/technicians/{id}`.

API routes to implement: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/profile`, `PATCH /api/profile`, and `PATCH /api/technician-profile`.

Use `app/Integrations/Supabase/Auth.php` for authentication and `app/Http/RequireAuth.php` for protected routes. Store private profile information in `profiles` and deliberately public technician information in `technician_profiles`. The starter schemas provide these tables. Create a profile after obtaining an authenticated identity; account creation may require email confirmation first.

Keep account authorization in the database. Users may select either account type at registration; ordinary profile updates must not change the stored account type. Passwords remain managed by Supabase Auth. Configure token persistence, refresh, confirmation callbacks and CSRF protection before exposing browser account forms.

Profile images use the `avatars` bucket and the Storage adapter. Public reviews must come from the Reviews module's completed-repair checks.
