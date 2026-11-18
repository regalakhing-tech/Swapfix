# Notifications Module

Status: module specification. Notification records and endpoints are not implemented in this starter. The PHPMailer adapter is available for future application email.

## README Features

- New messages and trade offers or counteroffers.
- Trade acceptance or rejection.
- Repair requests, quotations and quotation decisions.
- Additional-cost approval requests and decisions.
- Repair status changes, including Ready for Pickup.

Page: `/notifications`.

API routes to implement: `GET /api/notifications`, `PATCH /api/notifications/{id}/read`.

Add recipient, event type, related-record identifier, creation time and read state. RLS must restrict reads and read-state changes to the recipient. The business action determines recipients; users must not create arbitrary notifications addressed to another user. Link each notification to its actual conversation, offer or repair record and recheck authorization when opened.

In-app notifications are the specified core behavior. If application email is added, call PHPMailer only for authorized events and trusted recipient addresses. Use an idempotent outbox/retry design for reliable delivery rather than claiming a sent email merely because a record was saved.
