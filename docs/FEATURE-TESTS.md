# SwapFix Feature Test Report

Run date: November 19, 2026. Environment: Windows 11, PHP 8.5.0, Node 25.2.1.

## Summary

| Suite | Command | What it uses | Result |
| --- | --- | --- | --- |
| PHP application tests | `vendor/bin/phpunit` | Real routes and views, with mocked Supabase responses | 108 passed, 0 failed (1,163 assertions) |
| Database tests | `npm run test:database` | The real SQL schema on a local PGlite database | 43 passed, 0 failed |
| Page behavior script | `npm run test:ui` (first part) | The landing page from a local server, run in jsdom | Passed |
| Workspace behavior tests | `npm run test:ui` (second part) | `app.js` and `workspace.css` in jsdom | 7 passed, 0 failed |
| PHP syntax | `composer lint` | All application PHP files | 135 files passed |
| Live smoke test | `node tests/live-smoke.mjs` | A running site and the hosted Supabase data, signed in as one customer and one technician, read-only | 35 checks passed |
| Live form test | A script written and run by Codex CLI (`gpt-6.1-sol`), kept outside the repository | Real form submissions on https://swapfix.vercel.app as two customers and one technician, then cleanup | 51 passed, 0 failed |
| Browser tests | `npm run test:browser` | Playwright and axe in a real browser | **Not run** |

These results show the application logic and database rules pass locally, every page loads with the hosted data for both account types, and the main forms work on the deployed site. See [Live form test](#live-form-test) and [Not verified](#not-verified).

`npm run test:ui` reads `http://127.0.0.1:8000` by default. Another application was using that port during this run, so the suite ran with `SWAPFIX_TEST_ORIGIN` pointing at a separate local SwapFix server that had no credentials.

## Results by feature

### Landing page and public tabs

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Landing page loads without credentials | Status 200, honest message instead of records, cache header | `HttpTest::testHomeLoadsWithoutExternalCredentials` | Pass |
| Search, categories, and section links | Search posts to `/market`, every category link exists, every footer `/#` link has a target | `PublicPagesTest::testLandingPageReachesEveryCategoryAndItsOwnSections` | Pass |
| Buy, Exchange, and Repair landing tabs | Each tab shows its own steps and the newest live records, linked to their real pages, with no samples | `PublicPagesTest::testEveryLandingTabShowsItsStepsAndNewestLiveRecords` (3 tabs) | Pass |
| Landing page when Supabase fails | Says the latest posts could not load and shows no cards | `PublicPagesTest::testLandingPageSaysSoWhenLiveRecordsCannotLoad` | Pass |
| Browse tabs | One `h1`, guide, tips, quick filters, call to action, footer | `PublicPagesTest::testEveryBrowseTabAddsGuidanceAndQuickFilters` (3 tabs) | Pass |
| Quick filters | A chip applies its values, keeps other filters, resets the page, and toggles off | `PublicPagesTest::testQuickFilterAppliesItsValuesAndTogglesThemOff` | Pass |
| Technician invite | Hidden from signed-in customers | `PublicPagesTest::testRepairTabHidesTheTechnicianInviteFromCustomers` | Pass |
| Live cards, dialogs, mobile menu, image sizes, cache headers | Landing cards open real records, no samples, dialog links work without JavaScript | `tests/ui-behavior.mjs` | Pass |
| Search text is escaped | Script tags in a search do not render | `HttpTest::testSearchInputIsEscapedInTheRenderedPage` | Pass |

### Accounts and sign-in

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Sign-up email code | Code is sent, resent, and verified before a profile exists | `FeatureHttpTest::testSignupCodeResendsAndVerifiesBeforeCreatingProfile` | Pass |
| Expired forms | Sign-in, register, and verify forms reopen safely | `FeatureHttpTest::testExpiredRegistrationAndSignInFormsReopenWithoutSubmitting`, `testExpiredVerificationFormsOfferAFreshFormWithoutSendingOrVerifying` | Pass |
| CSRF and origin checks | Secure form, cross-origin writes rejected | `FeatureHttpTest::testSignInRendersSecureCsrfFormAndRejectsCrossOriginWrites`, `testVerificationAcceptsLoopbackOriginOnTheActualDevelopmentPort` | Pass |
| Signed-out publishing | Redirects to sign in | `FeatureHttpTest::testUnauthenticatedPublishingReturnsToSignIn` | Pass |
| Session cookies | Logout wins over refresh, new login wins, pending choices stay with one identity | `SessionCookiePrecedenceTest` (3 tests) | Pass |
| Token handling | Invalid token, auth outage, verified identity required | `AuthMiddlewareTest` (2 tests), `SupabaseTest` (5 tests) | Pass |
| Account type and private fields | Stored once, claims ignored, private fields stay private | `schema.test.mjs`: accounts | Pass |
| Profile editing and avatars | Email preference kept, avatars limited to the owner's folder | `WorkflowHttpTest::testEditingAProfileKeepsTheEmailPreferenceWhenTheControlIsUnavailable`, `schema.test.mjs`: accounts | Pass |
| Profile page | Account card beside grouped settings, technician link to the public page, CSRF field | `PublicPagesTest::testProfilePageShowsTheAccountCardBesideGroupedSettings` | Pass |

### Buy and sell

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Public listing pages | Dates, prices, escaped seller text, real empty state | `FeatureHttpTest::testPublicListingRendersDatesPricesAndEscapesSellerContent`, `testPublicMarketplaceShowsRealEmptyStateWithoutSampleRecords` | Pass |
| Create and edit listings | Owner-only edits, availability transitions, exact prices | `schema.test.mjs`: marketplace | Pass |
| Listing photos | Replacement at capacity, foreign paths rejected, non-images and oversized uploads stopped | `FeatureHttpTest::testListingEditorsAllowPhotoReplacementAtCapacity`, `PhotoUploadsTest`, `SupabaseTest::testUploadRejectsNonImageBeforeSendingAnything`, `testUploadUsesVerifiedUserFolderAndRandomFilename`, `schema.test.mjs`: marketplace, storage policies | Pass |
| Search | Filters, bounds, escaped input, sold and removed items hidden | `schema.test.mjs`: marketplace | Pass |
| Inquiries | Opens a participant conversation, blocks the owner | `schema.test.mjs`: marketplace | Pass |
| Publish, edit, reserve, sell, remove, inquire | Each form reaches its database function with the right values; an edit checks ownership first and keeps chosen photos | `WorkflowHttpTest::testFormActionReachesItsExpectedRpc` (7 cases) | Pass |
| Rejected input | An invalid price returns the form with the seller's text and saves nothing; a stale status change returns with the database message | `WorkflowHttpTest::testInvalidPriceIsRejectedBeforeAnythingIsSaved`, `testRejectedStatusChangeReturnsWithTheDatabaseMessage` | Pass |
| Back button | Detail pages link back to their section | `PublicPagesTest::testDetailPagesOfferAWayBackToTheirSection` | Pass |

### Exchange

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Offer rules | No self offers, no duplicate active offers, clear cash terms | `schema.test.mjs`: exchange | Pass |
| Counteroffers | Sides alternate, versions kept, stale decisions rejected | `schema.test.mjs`: exchange, review regressions | Pass |
| Agreement and completion | One accepted offer, completion is separate, cancel reopens | `schema.test.mjs`: exchange | Pass |
| Privacy | Outsiders cannot see negotiations, offerer can withdraw | `schema.test.mjs`: exchange | Pass |
| Offers in activity | Shows "Your turn" and "On hold" correctly | `FeatureHttpTest::testActivityShowsAnOfferOnHoldWhileAnotherOfferIsAgreed` | Pass |
| Form actions | List, edit, remove, offer, counter, accept, decline, withdraw, complete and cancel each reach their database function with the right values | `WorkflowHttpTest::testFormActionReachesItsExpectedRpc` (10 cases) | Pass |

### Repair

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Technician search | Area, specialty, and brand filters, no private fields | `schema.test.mjs`: repair workflow | Pass |
| Requests | No self-repair, technicians only | `schema.test.mjs`: repair workflow | Pass |
| Quotation approval | No work before approval, only assigned parties act, versions kept | `schema.test.mjs`: repair workflow | Pass |
| Added costs | Apply only after separate customer approval | `schema.test.mjs`: repair workflow, review regressions | Pass |
| Status changes | Fixed order, stale updates rejected, history recorded | `schema.test.mjs`: repair workflow | Pass |
| Cancellation | Rules applied, record retained | `schema.test.mjs`: repair workflow | Pass |
| Repair forms | Rejected input stays in its dialog, stale actions show the error | `RepairFormsHttpTest` (5 test methods) | Pass |
| Repair form actions | Request, quote, accept or decline the quote, request, approve or withdraw an added cost, update status, cancel, review, technician profile and services | `WorkflowHttpTest::testFormActionReachesItsExpectedRpc` (13 cases) | Pass |
| Technician-only writes | A customer posting a service is stopped before Supabase is called | `WorkflowHttpTest::testCustomerCannotPublishTechnicianServices` | Pass |
| Reviews | One review by the real customer after completion, ratings updated, invalid rating rejected | `schema.test.mjs`: reviews, `RepairFormsHttpTest::testInvalidReviewRatingIsRejectedWithoutChangingItToFiveStars` | Pass |

### Messages, notifications, and email

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Conversations | Participants only, unread counts, mark as read | `schema.test.mjs`: messaging and notifications | Pass |
| Unread badges | Clear on opening a thread, stay when an action is rejected | `CommunicationHttpTest` (4 tests) | Pass |
| Notifications | Older pages, mark all read, recipient only | `CommunicationHttpTest` (2 tests), `schema.test.mjs` | Pass |
| Email queue | Opted-in recipients only, worker role only | `schema.test.mjs`: messaging and notifications | Pass |
| Email building | Message prepared without sending, unsafe SMTP settings rejected | `MailTest` (3 tests) | Pass |

### Dashboards

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Customer dashboard | Banner, three counters, top bar, no extra database calls | `PublicPagesTest::testActivityDashboardUsesOnlyTheDataTheTabAlreadyLoads` | Pass |
| Technician dashboard | Four counters with correct progress bars | `PublicPagesTest::testTechnicianDashboardShowsEachGroupsShareOfJobs` | Pass |
| Technician access | Customers receive 403 | `FeatureHttpTest::testCustomerCannotUseTechnicianDashboard` | Pass |
| Activity paging | Older repairs load for both roles, tab is kept | `WorkflowHttpTest::testActivityLoadsOlderRepairsForBothRolesAndKeepsTheTabInPagination` | Pass |
| Shared interface behavior | Password toggle, dialogs, styled confirmation, avatar menu, upload limit, back-to-top arrow | `tests/workspace-behavior.test.mjs` | Pass |

### Database protection

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Row-level security | On for every table, API roles limited to reading | `schema.test.mjs`: schema exposure | Pass |
| Direct writes | Rejected, including anonymous changes | `schema.test.mjs`: schema exposure | Pass |
| Functions | No exposed `SECURITY DEFINER`, fixed `search_path`, limited execute rights | `schema.test.mjs`: schema exposure | Pass |
| Storage | Own-folder uploads, private repair and offer photos | `schema.test.mjs`: storage policies | Pass |
| Installer | Creates 18 tables and keeps unrelated records | `installer.test.mjs` | Pass |

### Demo sellers

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Demo seed script | Runs only while Confirm email is off, skips existing titles, turns off email notifications, removes the photo if a listing fails, valid seller and listing data | `DemoSellerSeedTest` (5 tests) | Pass |
| Live demo data | 4 sellers with 9 or 10 products and 2 swaps each, and 5 technicians with their services | Run of `scripts/seed-demo-sellers.php` on November 19, 2026; a repeat run changed nothing | Pass |
| Signed-in pages on hosted data | Customer and technician pages, the repair request form, records from each section, and the 403 for customers on technician pages | `tests/live-smoke.mjs` | Pass |

### Application basics

| Feature | What is checked | Test | Result |
| --- | --- | --- | --- |
| Health check | Separates app status from service readiness, exposes no secrets | `HttpTest::testHealthDistinguishesRuntimeFromIntegrationReadiness` | Pass |
| Error responses | JSON 404, 405 for wrong methods | `HttpTest::testUnknownApiRouteIsJson404`, `testUnsupportedHealthMethodIs405` | Pass |
| Private files | Project files are not served as pages | `HttpTest::testPrivateFilesDoNotResolveAsPublicRoutes` | Pass |
| Dates | Shown in the application time zone | `UiTest::testDatesUseTheApplicationTimezone` | Pass |

## Live form test

On November 19, 2026, Codex CLI (`gpt-6.1-sol`, xhigh reasoning) submitted the real HTML forms of https://swapfix.vercel.app with the demo accounts Mika Gadgets and Paolo Laptops (customers) and Jerome Phone Repair (technician). Each request loaded a fresh form, sent its CSRF token and hidden fields, and checked the page it redirected to.

| Area | Steps | Result |
| --- | --- | --- |
| Sign in | All three accounts | 3 passed |
| Profile | Change location, restore it exactly | 2 passed |
| Selling | Publish with a photo, public search, edit the price and keep the photo, Reserved, Available | 5 passed |
| Refusals | Own-listing inquiry, missing CSRF (403), customer posting a service (403), editing another seller's listing (404), signed-out new listing (redirect to sign-in) | 5 passed |
| Chat | Inquiry, follow-up, unread badge, badge clears on opening, reply, reply seen by the buyer | 6 passed |
| Trading | Publish a swap with a photo, offer with 500 cash, counteroffer of 800 with history kept, accept, cancel the agreement | 5 passed |
| Repair | Request with a service and photo, quotation of 1,500, accept, In Repair, added cost of 300 declined with the total kept at 1,500, cancel | 7 passed |
| Technician | Hide a service and confirm it leaves the public page, show it again, re-save the profile unchanged | 3 passed |
| Notifications | Open one through its link and mark all read, for each account | 6 passed |
| Cleanup | Remove the test listing and swap, confirm the profile and service are restored, confirm public search no longer shows test items | 9 passed |

The test left history in the demo accounts: a cancelled repair, a cancelled offer, the removed test listing and swap (hidden from the public), and three chat threads.

## Not verified

| Area | Why | How to check |
| --- | --- | --- |
| Page appearance at each screen size | No browser was operated, under the project rules | Follow the manual checks below at 1366, 1024, 768, 390, and 360 px |
| Browser and accessibility suite | Same reason | `npm run test:browser` |
| Completing a trade or repair, and posting a review, on the hosted project | These are permanent and reviews are public, so the live form test stopped before them | Covered by `schema.test.mjs` and `WorkflowHttpTest`; run the manual checks below to see them live |
| Real email delivery | No email was sent | Register with a real address, or run `scripts/mail-test.php` |
| Two people acting at the same moment | PGlite runs one connection, so true concurrency is not exercised | Test on a real PostgreSQL database |

## Manual checks

Use two accounts, one customer and one technician, in two separate browser profiles.

| # | Steps | Expected result | Pass? |
| --- | --- | --- | --- |
| 1 | Open `/`, search for a gadget, and open each preview tab | Results and steps change with the tab | ☐ |
| 2 | Register, then enter the emailed code | The account opens and onboarding finishes | ☐ |
| 3 | Sign out, then sign in | Confirmation appears first, then sign-in works | ☐ |
| 4 | Create a listing with photos, then edit it | It appears on `/market` with the changes | ☐ |
| 5 | Use each price and condition chip on `/market` | Results change, and a second click clears the chip | ☐ |
| 6 | As the other account, inquire about the listing | A conversation opens for both people | ☐ |
| 7 | Post an exchange and send an offer with cash | The owner sees the offer as "Your turn" | ☐ |
| 8 | Counter, accept, then complete the exchange | The status changes at each step | ☐ |
| 9 | As the technician, add a service and profile details | The technician appears on `/repair` | ☐ |
| 10 | As the customer, request a repair | It appears under New requests on the dashboard | ☐ |
| 11 | Send a quote and approve it | Work can start only after approval | ☐ |
| 12 | Request an added cost and decide on it | The total changes only if approved | ☐ |
| 13 | Move the repair to completed and leave a review | The rating appears on the technician page | ☐ |
| 14 | Open messages and notifications | Badges clear after reading | ☐ |
| 15 | Resize each page from desktop to 360 px | No sideways scrolling, and the tab bar appears below 1024 px | ☐ |

## Run the tests again

```powershell
composer install
npm install
vendor/bin/phpunit
npm run test:database
php -S 127.0.0.1:8010 -t public public/router.php
```

In a second terminal:

```powershell
$env:SWAPFIX_TEST_ORIGIN = "http://127.0.0.1:8010"
npm run test:ui
```
