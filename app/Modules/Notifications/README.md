# Notifications Module

Browser workflows are implemented and registered in `routes/web.php` (the mail worker is in `routes/api.php`).

Files: NotificationController.php, NotificationRepository.php, MailDispatcher.php.

Routes: /notifications, /internal/mail/dispatch.

Business events create recipient-owned in-app notifications. Optional SMTP dispatch claims the durable outbox with a server-only role and secret endpoint. SMTP remains separately configured; tests do not send messages.

All mutations call guarded Supabase RPCs; templates are under `resources/views/`. See [setup](../../../docs/SETUP.md) and [database](../../../supabase/README.md) for configuration and verification.
