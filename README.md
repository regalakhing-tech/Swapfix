# SwapFix

**A Web-Based Marketplace for Gadget Sales, Exchanges, and Repair Services**

This document describes SwapFix's users, features, pages, workflows, and functional requirements. The PHP application implements the core workflows below; hosted configuration and verification are tracked separately in the setup guide.

### PHP Application

The project includes customer and technician accounts, product listings, exchange offers and counteroffers, repair quotations and added-cost approval, tracking, contextual chat, notifications, reviews and activity history. PHP and Slim render the pages. Supabase provides Auth, PostgreSQL and Storage. PHPMailer supports optional SMTP notifications, and Vercel runs the PHP application in a FrankenPHP container.

The public homepage uses the transparent SwapFix logo, blue and teal brand colors, a generated gadget hero and sample photos from RussDtech. Its illustrative catalog is separate from live inventory at `/market`, `/exchange` and `/repair`. New projects begin with empty live lists. Participants arrange payments and handovers through chat.

- [Setup and deployment instructions](docs/SETUP.md)
- [Folder structure and module responsibilities](docs/FILE_STRUCTURE.md)
- [Supabase schema and storage setup](supabase/README.md)
- [UI direction and reference styles](docs/UI-DIRECTION.md)
- [Image assets and optional UI checks](docs/UI-ASSETS.md)

```powershell
composer install
Copy-Item .env.example .env
composer dev
```

Open `http://127.0.0.1:8000`. Preserve an existing `.env` instead of copying over it. Configure the selected Supabase project and a unique `APP_KEY`. Optional SMTP credentials enable application email. See [SETUP](docs/SETUP.md) for PHP, database, DOM and browser checks.

## 1. Project Overview

SwapFix brings gadget buying, selling, exchanging, and repair services into one website. Gadget owners can list secondhand devices, offer them for trade, or request repairs from technicians. Technicians can promote services, provide quotations, manage repair jobs, and participate in the marketplace.

For example, the owner of a phone with a cracked screen can use SwapFix to:

- Request a screen replacement from a technician.
- Sell the phone as-is while disclosing its condition and defects.
- Offer the phone for exchange and specify the gadget wanted in return.

Product purchases, exchanges, and repairs each have their own records and conversations so users can follow the relevant details from inquiry to completion.

## 2. Objectives

- Provide one platform for gadget sales, exchanges, and repairs.
- Help buyers understand a device's condition, known defects, price, and location.
- Make trade offers and additional cash proposals clear to both parties.
- Help customers find technicians by location, specialty, and supported device brand.
- Record repair quotations, customer approvals, progress updates, and repair history.
- Keep related messages and notifications connected to the correct transaction or repair.
- Build trust through public technician profiles and reviews from completed repairs.

## 3. Users and Permissions

| Capability | Customer / Gadget Owner | Technician |
| --- | --- | --- |
| Register, log in, and manage a personal profile | Yes | Yes |
| Browse, buy, sell, and exchange gadgets | Yes | Yes |
| Create listings for gadgets, accessories, and spare parts | Yes | Yes |
| Send purchase inquiries and trade offers | Yes | Yes |
| Find technicians and request repairs as a customer | Yes | Yes |
| Review quotations and track requested repairs | Yes | Yes |
| Maintain a public technician profile | No | Yes |
| Publish repair services and quotations | No | Yes |
| Manage repair jobs and update their progress | No | Yes |
| Review a technician after receiving a completed repair | Yes | Yes, when acting as the repair customer |

A technician retains the marketplace capabilities of a customer. Technician-specific features are available through the Repair section and technician dashboard.

## 4. Main Navigation

The three primary tabs are:

| Tab | Purpose | Main actions |
| --- | --- | --- |
| **Buy & Sell** | Browse and manage product listings | Search, filter, view products, inquire, create listings, and manage availability |
| **Exchange** | Find gadgets available for trade | View wanted gadgets, send offers, propose cash adjustments, and respond to offers |
| **Repair** | Find repair services and manage repair work | Find technicians, request repairs, review quotations, track progress, and manage jobs |

Supporting navigation provides access to Messages, Notifications, My Activity, and Profile. Technicians can access their services and job dashboard from Repair.

## 5. Accounts and Profiles

### 5.1 Registration and Login

- Users can create either a customer account or a technician account.
- Registered users can log in and log out.
- Account permissions determine access to technician features and private records.

### 5.2 Profile Management

Both account types can update:

- Name.
- Location.
- Profile photo.

### 5.3 Public Technician Profiles

A technician profile displays:

- Technician name, photo, and location.
- Repair specialties, such as screen replacement or software troubleshooting.
- Supported device brands and models, where applicable.
- Service area.
- Available hours.
- Published repair services and estimated prices.
- Customer ratings and reviews from completed repairs.

Customers can open a technician's profile and select a service or start a repair request.

## 6. Gadget Marketplace: Buy & Sell

### 6.1 Product Categories

- Cellphones.
- Tablets.
- Laptops.
- Accessories.
- Spare parts.

### 6.2 Create Product Listings

A listing includes the following information where applicable:

| Field | Description |
| --- | --- |
| Title and description | A clear summary of the item and what is included |
| Photos | Images showing the item and any visible defects |
| Category | The type of gadget, accessory, or spare part |
| Brand and model | Device or product identification |
| Condition | The item's current physical and working condition |
| Known defects | Disclosed faults, damage, missing parts, or functional issues |
| Price | The seller's asking price |
| Location | The area used for browsing and arranging a transaction |
| Availability | Available, Reserved, or Sold |

### 6.3 Browse, Search, and Filter

- Browse available product listings.
- Search using product names, brands, models, or relevant keywords.
- Filter by gadget type, brand, condition, price, and location.
- Open a listing to inspect photos, details, defects, and seller information.

### 6.4 Purchase Inquiries

- Buyers can send an inquiry from a listing.
- The conversation stays associated with that product.
- Buyer and seller can discuss the price, payment method, and meetup or delivery arrangements through chat.

### 6.5 Listing Management

Listing owners can:

- Edit listing details and photos.
- Mark an item as Reserved or Sold.
- Remove a listing.
- View their listings and related conversations through My Activity.

Sold and removed items no longer appear as available inventory. A reserved item must clearly show its availability status.

## 7. Gadget Exchange

### 7.1 Create an Exchange Listing

- Post the gadget being offered with photos, brand, model, condition, known defects, and location.
- Describe the device or type of gadget wanted in return.
- State whether an additional cash amount may be considered.

### 7.2 Send a Trade Offer

A trade offer contains:

- Details and photos of the gadget being offered.
- Its condition and known defects.
- A message explaining the proposal.
- An optional additional cash amount, including which party would pay it.

### 7.3 Respond to Offers

The listing owner can:

- Accept an offer.
- Decline an offer.
- Make a counteroffer with revised gadget or cash terms.

Both parties can discuss the exchange through its associated conversation. A counteroffer must show the revised terms and who needs to respond.

### 7.4 Complete an Exchange

- Acceptance records agreement on the proposed terms.
- The parties arrange the exchange through chat.
- After the trade takes place, the owner closes the exchange listing.
- A completed exchange no longer appears as available for trade.

Accepting an offer and completing the physical exchange are separate actions. Only one offer may be the accepted offer for an exchange at a time; other offers must not appear accepted or completed as a result.

## 8. Repair Services and Quotations

### 8.1 Publish Repair Services

Technicians can publish services such as:

- Screen replacement.
- Battery replacement.
- Charging port repair.
- Software troubleshooting.

A service can include a description, supported brands and models, and an estimated price. The service estimate provides a starting point; the customer's repair quotation records the proposed cost for the specific request.

### 8.2 Find a Technician

Customers can search or filter technicians by:

- Location or service area.
- Repair specialty.
- Supported device brand.

Customers can review a technician's profile, available hours, published services, ratings, and reviews before sending a request.

### 8.3 Submit a Repair Request

The customer selects a technician and provides:

- Device brand and model.
- A description of the problem and relevant symptoms.
- Photos, if available.
- The requested repair service, if known.

The technician receives the request and can ask follow-up questions in the associated conversation.

### 8.4 Send and Review a Quotation

The technician provides:

- Estimated repair cost.
- Expected completion time.
- Repair details, including the proposed work and parts where relevant.

The customer can accept or decline the quotation. The quotation and response remain part of the repair record. A declined quotation does not authorize repair work.

### 8.5 Approve Added Costs

If inspection reveals another issue that changes the original estimate:

1. The technician explains the additional issue and proposed work.
2. The technician sends the proposed additional amount and revised total, including any change to the completion estimate.
3. The customer accepts or declines the added cost.
4. Work that incurs the added cost proceeds only after customer approval.

The original quotation, proposed changes, and customer decisions remain visible in the repair record. A pending or declined additional cost must not silently replace the agreed price.

## 9. Repair Tracking and Records

### 9.1 Main Repair Status Flow

```text
Request Sent -> Quotation Sent -> Accepted -> In Repair -> Ready for Pickup -> Completed
```

| Status | Meaning | Trigger |
| --- | --- | --- |
| Request Sent | The selected technician has received the customer's request | Customer submits the request |
| Quotation Sent | A repair proposal is waiting for the customer's decision | Technician sends a quotation |
| Accepted | The customer has approved the quotation | Customer accepts the quotation |
| In Repair | The technician has started the agreed work | Technician starts the repair |
| Ready for Pickup | The agreed repair work is finished and the device is ready | Technician updates the job |
| Completed | The repair and device handover have been completed | Technician records completion after handover |

This is the successful repair path. A declined quotation is recorded as a separate decision and does not advance the request to Accepted. Additional-cost approval is tracked separately from the main repair status.

### 9.2 Drop-Off and Pickup

- Customers and technicians arrange drop-off and pickup through chat.
- Both parties can refer to the repair request and quotation in the conversation.
- The customer receives a notification when the device is ready for pickup.

### 9.3 Repair History

Each repair record includes:

- Customer and technician.
- Device details and reported problem.
- Submitted photos, if any.
- Quotation details and customer decision.
- Approved additional costs and the agreed total price.
- Status updates with timestamps.
- Completion date when completed.
- A related customer review, if submitted.

### 9.4 Technician Dashboard

The dashboard groups work into:

- **New requests:** requests awaiting review or quotation.
- **Awaiting customer response:** quotations or added-cost requests awaiting a decision.
- **Active jobs:** accepted repairs, work in progress, and devices ready for pickup.
- **Completed repairs:** finished jobs and their records.

Technicians can open a job to review device details, send a quotation, request approval for added costs, send messages, and update progress.

## 10. Messaging, Notifications, and Reviews

### 10.1 In-App Messaging

- Users can communicate about a product listing, trade offer, or repair request.
- Each conversation retains its related context so users can identify the item or job being discussed.
- Conversations support price discussions, trade negotiations, repair questions, and handover arrangements.

### 10.2 Notifications

| Event | Recipient |
| --- | --- |
| New message | The other conversation participant |
| New trade offer or counteroffer | The user expected to respond |
| Trade offer accepted or declined | The user who made the offer being answered |
| New repair request | The selected technician |
| Repair quotation sent | The requesting customer |
| Quotation accepted or declined | The technician |
| Additional-cost approval requested | The customer |
| Additional-cost request accepted or declined | The technician |
| Repair status changed | The customer |

Notifications should link to the related conversation, offer, quotation, or repair record.

### 10.3 Ratings and Reviews

- Customers can rate and review a technician after a completed repair.
- Reviews appear on the technician's public profile.
- Each review is associated with the completed repair that qualifies it.
- A user cannot review a repair they did not receive or review their own technician service.

### 10.4 Activity History

My Activity provides access to:

- The user's product and exchange listings.
- Trade offers sent and received.
- Repair requests and quotation decisions.
- Active and completed repairs.
- Related conversations and submitted reviews.

## 11. Main Pages and Screens

| Page or screen | Main content and actions |
| --- | --- |
| Registration and Login | Account creation, account type selection, and login |
| Profile Settings | Edit name, location, and profile photo |
| Buy & Sell | Product search, filters, and listing results |
| Product Details | Photos, specifications, defects, price, availability, and inquiry action |
| Create / Edit Product Listing | Add or update product information and photos |
| Exchange | Browse gadgets offered for trade |
| Exchange Details | Offered gadget, wanted gadget, offers, and trade discussion |
| Trade Offer Details | Gadget information, photos, cash terms, and response actions |
| Repair / Find a Technician | Technician search and filters |
| Public Technician Profile | Specialties, brands, area, hours, services, and reviews |
| Repair Request Form | Device details, problem description, and optional photos |
| Repair Details | Quotation, approval decisions, status history, messages, and review eligibility |
| Technician Services | Create and manage published repair services |
| Technician Dashboard | New requests, quotations, active jobs, and completed work |
| Messages | Context-linked conversations |
| Notifications | Messages, offers, quotation decisions, and repair updates |
| My Activity | Personal listings, offers, repair requests, and history |

## 12. Core Workflows

### 12.1 Buy or Sell a Gadget

1. A seller creates a product listing with photos and condition details.
2. A buyer searches or filters listings and opens the product.
3. The buyer sends a purchase inquiry.
4. Both parties discuss price, payment, and meetup or delivery.
5. The seller marks the listing Reserved when appropriate.
6. After the transaction is completed, the seller marks it Sold.

### 12.2 Exchange a Gadget

1. An owner posts a gadget and describes what they want in return.
2. Another user submits a trade offer with photos and optional cash terms.
3. The owner accepts, declines, or makes a counteroffer.
4. The parties agree on the terms and arrange the exchange.
5. After handover, the owner closes the listing as completed.

### 12.3 Request and Complete a Repair

1. A customer finds a technician by location, specialty, or device brand.
2. The customer submits device details, the problem, and optional photos.
3. The technician reviews the request and sends a quotation.
4. The customer accepts or declines the quotation.
5. After acceptance and device drop-off, the technician starts the repair.
6. Any additional cost requires a separate customer decision before that work proceeds.
7. The technician updates the job to Ready for Pickup when the work is finished.
8. The parties arrange pickup, complete handover, and record completion.
9. The customer can submit a rating and review.

## 13. Main Data Records

The following are conceptual records for implementation planning. They do not prescribe a database technology or final schema.

| Record | Information to retain |
| --- | --- |
| User | Account identifier, account type, name, location, profile photo, and authentication reference |
| Technician Profile | Technician identifier, specialties, supported brands, service area, and available hours |
| Product Listing | Owner, category, title, description, brand, model, condition, defects, price, location, photos, and availability |
| Exchange Listing | Owner, offered device details, photos, wanted gadget, cash preferences, and exchange status |
| Trade Offer | Exchange listing, sender, offered gadget, photos, cash amount and payer, message, response, and counteroffer history |
| Repair Service | Technician, service description, supported models, and estimated price |
| Repair Request / Job | Customer, technician, device, issue, photos, current status, and completion date |
| Repair Quotation | Repair request, proposed work, estimated cost, expected completion time, and customer decision |
| Additional-Cost Request | Repair job, reason, proposed additional amount, revised total, timing changes, and customer decision |
| Repair Status Update | Repair job, status, timestamp, and responsible user |
| Conversation and Message | Participants, related listing or repair context, sender, content, and timestamp |
| Notification | Recipient, event type, related record, timestamp, and read state |
| Review | Completed repair, customer, technician, rating, written feedback, and submission date |

## 14. Business Rules and Access Control

- Users manage only their own profiles and listings.
- Only technician accounts publish repair services, issue quotations, and manage jobs assigned to them.
- Repair requests, quotation decisions, and conversations are accessible only to their authorized participants.
- A user cannot send a purchase inquiry or trade offer to their own listing, or request a repair from their own technician profile.
- Device condition, known defects, and cash adjustments must be visible before a user agrees to a transaction.
- Sold, completed, and removed listings must not appear as available items.
- A quotation requires customer acceptance before the repair starts.
- Added costs require explicit customer approval before the additional work proceeds.
- Repair status changes must follow the supported workflow and retain their history.
- Reviews require a completed repair and must be tied to its actual customer and technician.
- Private messages and repair records must not appear on public technician profiles.

## 15. Quality Requirements

- **Responsive interface:** Core tasks should work on mobile phones, tablets, and desktop browsers.
- **Clear navigation:** Buy & Sell, Exchange, and Repair should remain easy to identify.
- **Input validation:** Required fields, prices, cash amounts, and uploaded images must be checked with helpful error messages.
- **Secure accounts:** Protect credentials, sessions, and private records; enforce permissions on the server.
- **Safe uploads:** Restrict photo uploads to supported image types and reasonable file sizes.
- **Consistent records:** Preserve quotations, approval decisions, and repair updates accurately.
- **Accessible controls:** Use readable text, labeled forms, keyboard-accessible actions, and clear status labels.
- **Useful feedback:** Provide loading, empty, error, and success states for searches, submissions, and updates.

## 16. Scope and Planning Assumptions

The core scope includes the two account types, marketplace, exchanges, repair services and quotations, repair tracking, messaging, notifications, reviews, and activity history described above.

The following assumptions clarify the supplied requirements:

- Payment methods and meetup or delivery arrangements are discussed through chat. Integrated checkout, payment processing, and escrow are not specified.
- No courier booking or live delivery tracking integration is specified.
- A technician can use the marketplace and request another technician's services as a customer.
- Technician identity verification, administrator tools, dispute handling, and warranty management are not specified as core features.
- SwapFix uses PHP, Supabase, PHPMailer and Vercel. Prices are in Philippine pesos. The app limits the combined images in a submission to 4 MiB with the default configuration.
- Reviews use a one-to-five-star scale. Declined quotations stay in the repair history, and the technician can send a revised quotation.

## 17. Implementation Checklist

Each checked item has an application entry point and local PHP, database, or browser coverage. Hosted credentials, mail delivery, and deployment checks are documented in [SETUP](docs/SETUP.md).

- [x] Users can register and log in as a customer or technician.
- [x] Both roles can edit their name, location, and profile photo.
- [x] Technician profiles show specialties, supported brands, service area, hours, and reviews.
- [x] Users can create listings for gadgets, accessories, and spare parts with photos and complete item details.
- [x] Listings can be searched and filtered by gadget type, brand, condition, price, and location.
- [x] Buyers can send product inquiries and discuss transaction arrangements through chat.
- [x] Owners can edit, reserve, sell, or remove their listings.
- [x] Users can publish exchange listings with wanted-gadget details.
- [x] Trade offers support device details, photos, optional cash terms, acceptance, rejection, and counteroffers.
- [x] Completed exchanges stop appearing as available.
- [x] Technicians can publish repair services with supported models and estimated prices.
- [x] Customers can find technicians by location, specialty, and brand.
- [x] Customers can submit repair requests with device details, problem descriptions, and optional photos.
- [x] Technicians can send quotations with cost, completion estimates, and repair details.
- [x] Customers can accept or decline quotations and additional-cost requests.
- [x] Repairs follow the defined status flow and retain update history.
- [x] Customers and technicians can arrange drop-off and pickup through chat.
- [x] Repair history shows device details, agreed price, updates, and completion date.
- [x] The technician dashboard separates new requests, pending decisions, active jobs, and completed repairs.
- [x] Messages remain associated with the relevant product, trade offer, or repair request.
- [x] Notifications cover messages, offers, quotations, approval decisions, and repair status changes.
- [x] Customers can rate and review technicians after completed repairs.
- [x] My Activity brings together the user's listings, offers, and repair requests.
- [x] Access controls protect ownership, private conversations, and repair records.
- [x] The main navigation provides Buy & Sell, Exchange, and Repair tabs.
