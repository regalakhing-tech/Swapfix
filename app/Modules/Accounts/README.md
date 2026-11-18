# Accounts Module

Browser workflows are implemented and registered in `routes/web.php` (the mail worker is in `routes/api.php`).

Files: AuthController.php, AccountController.php, AccountRepository.php.

Routes: /register, /login, /onboarding, /account.

Supabase Auth manages credentials. Pending onboarding is bound to the verified email. The account type is immutable after onboarding; private settings live in profile_private. Public technician profiles are maintained by the Repair module.

All mutations call guarded Supabase RPCs; templates are under `resources/views/`. See [setup](../../../docs/SETUP.md) and [database](../../../supabase/README.md) for configuration and verification.
