# Activity Module

Status: module specification. The activity page and API are not implemented in this starter.

## README Features

- Personal product and exchange listings.
- Trade offers sent and received.
- Repair requests, quotation decisions and current repair progress.
- Completed repair history, conversations and reviews.

Page: `/activity`.

API route to implement: `GET /api/activity`, with bounded filters or separate paginated queries for listings, offers and repairs.

Build this module after its source records exist. Use verified request identity and the participant/ownership rules of each source module. Retain access to an owner's sold or removed listings where the record remains stored. Completion history should use the stored agreed amount and completion timestamp.

If combining sources in a PostgreSQL view, use an RLS-respecting security-invoker view and review its grants. An activity aggregation must not make another user's private conversations, offers or repairs visible.
