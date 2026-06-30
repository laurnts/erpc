# Change: Add Customer Portal for Self-Service Request Submission

## Why

Buyers currently submit goods and service requests via WhatsApp and email. Internal staff must manually re-enter this information into ERPC. This creates delays, transcription errors, and no self-service visibility for customers.

A dedicated **Customer Portal** allows buyer contacts to log in, submit requests (manual entry or document upload), and track progress — while internal teams continue working in the existing admin panel on the same `Request` records.

This reverses the original ERP non-goal ("customer portal — admin-only for now") now that the core Request workflow is stable.

## What Changes

- **ADDED**: Filament `customer` panel at `{APP_URL}/customer/login` (path-based, configurable via `CUSTOMER_PATH`)
- **ADDED**: Customer user accounts linked to buyer `Company` records (invite-based onboarding)
- **ADDED**: Customer-facing request submission wizard (manual items **or** document upload)
- **ADDED**: Customer-facing request list and detail with sanitized progress timeline
- **ADDED**: `submission_method` and `submitted_at` on requests; `CreationSource::PORTAL` for portal-originated records
- **ADDED**: Internal admin indicators for portal-submitted requests and notifications to team
- **ADDED** (Phase 2): Customer quote accept/reject and PO upload from portal
- **ADDED** (Phase 2): Email notifications to customers on stage changes
- **MODIFIED**: `User::canAccessPanel()` to separate `app` (internal) and `customer` (buyer) access
- **MODIFIED**: Supplier confidentiality enforced for all customer-facing views (no supplier quotes, PNL, QE, or internal notes)

## Impact

- **Affected specs**: `customer-portal` (new), `authentication`, `erp-trading-core`, `buyer-quotes`
- **Affected code** (high level):
  - `app/Providers/Filament/CustomerPanelProvider.php` — new panel
  - `app/Models/User.php` — panel access rules
  - `app/Models/CompanyPortalUser.php` or `company_user` pivot — buyer access linkage
  - `app/Models/Request.php` — submission fields
  - `app/Filament/Customer/` — portal resources and pages
  - `app/Policies/CustomerRequestPolicy.php` — scoped authorization
  - `app/Filament/Resources/BuyerResource.php` — invite portal user action
  - `config/app.php` — `customer_path` config
  - Database migrations for portal access pivot and request fields
- **Breaking changes**: None for existing internal users or requests (backward compatible defaults)

## Phased Delivery

| Phase | Scope |
|-------|--------|
| **Phase 1 (MVP)** | Panel, login, invite, manual request submit, list/detail with progress timeline, admin visibility |
| **Phase 2** | Document upload submission, quote accept/reject, PO upload, customer email notifications |
| **Phase 3** | Shipment/DO visibility, team branding on portal, dashboard widgets |

Implementation SHALL NOT begin until this proposal is reviewed and approved.
