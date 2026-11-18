# Marketplace Module

Status: module specification. The starter includes `product_listings` SQL and the shared Data API / Storage adapters; listing pages and business endpoints are not registered yet.

## README Features

- Cellphone, tablet, laptop, accessory and spare-part listings.
- Photos, brand, model, condition, known defects, price and location.
- Search and filters by gadget type, brand, condition, price and location.
- Product-specific purchase inquiries and chat.
- Editing, reservation, sale and removal by the owner.

## Intended Pages and API

Pages: `/marketplace`, `/listings/{id}`, `/listings/create`, `/listings/{id}/edit`.

API routes to implement: `GET /api/listings`, `GET /api/listings/{id}`, `POST /api/listings`, `PATCH /api/listings/{id}`, `DELETE /api/listings/{id}`, and `POST /api/listings/{id}/inquiries`.

Use the starter `product_listings` table. Associate listing-specific conversations through Messaging when implemented. Use the `listing-images` bucket; enforce file-upload checks in the controller before calling the Storage adapter.

Build Data API query parameters from an explicit field/filter allowlist, with pagination and bounded limits. Take `owner_id` from verified request identity. Only owners can manage a listing. Public browsing includes Available and clearly labeled Reserved items; Sold and Removed items are excluded. A purchase inquiry does not represent a completed payment or sale.

Deleting or changing a listing requires an image-lifecycle decision. Public image URLs remain public until the objects are removed; avoid deleting shared images that another record still references.
