# Messaging Module

Status: module specification. Conversations, messages and endpoints are not implemented in this starter.

## README Features

- In-app conversations associated with a product, trade offer or repair request.
- Price negotiations, payment/meetup/delivery arrangements, trade discussions and repair questions.
- Drop-off and pickup arrangements.

Pages: `/messages`, `/messages/{conversationId}`.

API routes to implement: `GET/POST /api/conversations`, `GET/POST /api/conversations/{id}/messages`.

Add `conversations`, `conversation_participants` and `messages`, with the originating context and timestamped authorship. Create participants from the verified listing/offer/job relationship; do not accept arbitrary participant IDs from a request. Use participant RLS for reads and writes, derive sender identity from the token, paginate history and escape displayed text.

Create in-app notification records when a message is committed. Supabase Realtime can be considered when the messaging implementation is built; polling or realtime delivery is not installed by this starter.
