# Repair Module

Status: module specification. Public technician-profile SQL exists; services, jobs, quotations and repair endpoints are not implemented in this starter.

## README Features

- Technician service publishing, supported brands/models and estimated prices.
- Technician search by location, specialty and brand.
- Requests with device details, problem descriptions and optional photos.
- Quotations with work details, estimated cost and expected completion time.
- Customer acceptance/rejection and separate approval of added costs.
- Drop-off and pickup discussions, progress tracking and repair history.
- Technician dashboard for new requests, pending responses, active jobs and completed repairs.

## Intended Pages and API

Pages: `/repair`, `/repair/request/{technicianId}`, `/repairs/{id}`, `/technician/dashboard`, `/technician/services`.

API routes to implement: `GET /api/technicians`, `GET/POST /api/repair-services`, `POST /api/repairs`, `GET /api/repairs/{id}`, `POST /api/repairs/{id}/quotations`, `POST /api/quotations/{id}/accept`, `/decline`, `POST /api/repairs/{id}/additional-costs`, additional-cost decision routes, and `POST /api/repairs/{id}/status`.

Add `repair_services`, `repair_jobs`, `repair_quotations`, `additional_cost_requests` and `repair_status_updates`. Relate reviews and conversations to the job. Retain original quotations and each customer decision; changing an estimate must not overwrite an already agreed amount.

## Status and Approval Rules

```text
Request Sent -> Quotation Sent -> Accepted -> In Repair -> Ready for Pickup -> Completed
```

Only the requesting customer can accept/decline their quotation or added costs. Only the assigned technician can publish quotations and update their jobs. Check the stored technician account type. Block work before initial approval and block added work until the additional cost is accepted. Apply decisions and status-history changes atomically; prevent skipped or stale transitions. The agreed total includes approved amounts only.

Use `repair-images`, which is private. The starter bucket policy only permits the uploading owner. Add job-participant RLS and protected or expiring downloads for the assigned technician when implementing repair requests. Never include private photos on a public technician profile.

Completion records the device handover date and enables a customer review. Declined quotations, cancellation and re-quotation rules require an explicit product decision before those paths are implemented.
