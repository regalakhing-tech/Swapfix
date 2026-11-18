# Reviews Module

Status: module specification. Review records and endpoints are not implemented in this starter.

## README Features

- Customer ratings and written feedback after a completed repair.
- Public reviews on the relevant technician profile.

Pages: review form in `/repairs/{id}` and review history on `/technicians/{id}`.

API routes to implement: `POST /api/repairs/{id}/review`, `GET /api/technicians/{id}/reviews`.

Add a `reviews` table linked to a completed repair, its customer and assigned technician, with rating, feedback and submission timestamp. Decide and document the rating scale before implementing validation. Enforce one review per repair and verify completion and customer identity in the database write path. A technician acting as a customer may review another technician, but cannot review their own service.

Expose the review and intentionally public reviewer display fields only. Never join private account details into public profile responses. Compute technician aggregates from eligible reviews.
