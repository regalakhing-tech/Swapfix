# Marketplace Module

Browser workflows are implemented and registered in `routes/web.php` (the mail worker is in `routes/api.php`).

Files: ListingController.php, ListingRepository.php.

Routes: /market, /market/new, /market/{id}.

Search supports gadget type, brand, condition, price and location. Only owners can edit or reserve/sell/remove listings. Inquiries create participant conversations. Availability transitions are checked under database row locks.

All mutations call guarded Supabase RPCs; templates are under `resources/views/`. See [setup](../../../docs/SETUP.md) and [database](../../../supabase/README.md) for configuration and verification.
