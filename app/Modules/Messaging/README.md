# Messaging Module

Browser workflows are implemented and registered in `routes/web.php` (the mail worker is in `routes/api.php`).

Files: MessageController.php, MessageRepository.php.

Routes: /messages, /messages/{id}.

Conversations retain their listing, trade-offer or repair context. Only participants can read/send messages. Unread counts are stored in PostgreSQL and updated through guarded RPCs.

All mutations call guarded Supabase RPCs; templates are under `resources/views/`. See [setup](../../../docs/SETUP.md) and [database](../../../supabase/README.md) for configuration and verification.
