# Exchange Module

Browser workflows are implemented and registered in `routes/web.php` (the mail worker is in `routes/api.php`).

Files: ExchangeController.php, ExchangeRepository.php, OfferController.php, OfferActions.php, OfferForm.php.

Routes: /exchange, /exchange/new, /exchange/{id}, /offers/{id}.

Offers include device details, photos and explicit cash payer. Counteroffers alternate between participants, preserve immutable terms and require the current version for a decision. Acceptance, cancellation and completed handover are separate states.

All mutations call guarded Supabase RPCs; templates are under `resources/views/`. See [setup](../../../docs/SETUP.md) and [database](../../../supabase/README.md) for configuration and verification.
