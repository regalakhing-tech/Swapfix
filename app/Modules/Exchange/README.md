# Exchange Module

Status: module specification. Exchange tables, pages and endpoints are not implemented in this starter.

## README Features

- Trade listings with device details, condition, defects, photos and wanted gadgets.
- Offers containing gadget details and photos.
- Optional cash adjustments with an explicit payer.
- Accept, decline and counteroffer actions.
- Completion after the physical exchange and removal from available results.

## Intended Pages and API

Pages: `/exchange`, `/exchange/{id}`, `/exchange/create`, `/trade-offers/{id}`.

API routes to implement: `GET/POST /api/exchanges`, `GET/PATCH /api/exchanges/{id}`, `POST /api/exchanges/{id}/offers`, `POST /api/trade-offers/{id}/accept`, `/decline`, `/counter`, and `POST /api/exchanges/{id}/complete`.

Add `exchange_listings`, `trade_offers` and counteroffer-history records. Store money as an exact numeric amount with an explicit payer and currency. Link conversations to their exchange or offer. Use the `listing-images` bucket for deliberately public gadget photos.

Only offer participants can read private negotiations. Users cannot offer on their own listing. Acceptance must be atomic, enforce at most one accepted offer, and prevent accepting stale counteroffer terms. Record who must respond next. Acceptance is distinct from completed handover; close availability only through the defined exchange workflow.
