# Activity Module

Browser workflows are implemented and registered in `routes/web.php` (the mail worker is in `routes/api.php`).

Files: ActivityController.php, ActivityRepository.php.

Routes: /activity.

The user's listings, exchanges, offers, repairs, conversations and reviews are read through ownership/participant policies. This page uses real records, separate from the illustrative homepage.

All mutations call guarded Supabase RPCs; templates are under `resources/views/`. See [setup](../../../docs/SETUP.md) and [database](../../../supabase/README.md) for configuration and verification.
