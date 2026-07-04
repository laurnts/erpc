# Implementation Tasks

> **Approval gate**: Do not start until `proposal.md` and spec deltas are reviewed and approved.

## Phase 1 — MVP (Portal Login, Manual Submit, Progress View)

### 1. Foundation & Configuration
- [x] 1.1 Add `customer_path` and `customer_portal_enabled` to `config/app.php`
- [x] 1.2 Create `CustomerPanelProvider` with `->path('customer')`, login, password reset, email verification
- [x] 1.3 Register `CustomerPanelProvider` in `bootstrap/providers.php`
- [x] 1.4 Create `app/Filament/Customer/` namespace for portal resources and pages

### 2. Database Schema
- [x] 2.1 Create migration for `company_portal_users` pivot table
- [x] 2.2 Create migration to add `submission_method`, `submitted_at`, `submitted_by_user_id` to `requests`
- [x] 2.3 Add `PORTAL` case to `CreationSource` enum

### 3. Models & Relationships
- [x] 3.1 Create `CompanyPortalUser` pivot model (or use custom pivot class)
- [x] 3.2 Add `portalUsers()` relationship on `Company`
- [x] 3.3 Add `portalCompanies()` relationship on `User`
- [x] 3.4 Add `hasActivePortalAccess()` and `belongsToAnyInternalTeam()` helpers on `User`
- [x] 3.5 Update `User::canAccessPanel()` for `app` vs `customer` split
- [x] 3.6 Update `Request` model with new fields, casts, and `submittedBy()` relationship
- [x] 3.7 Create `RequestSubmissionMethod` enum (`manual`, `document`)

### 4. Authorization
- [x] 4.1 Create `CustomerRequestPolicy` scoped to user's portal buyer companies
- [x] 4.2 Register policy for customer panel context
- [x] 4.3 Create middleware or service to resolve portal `team_id` and `company_id` context

### 5. Admin — Portal User Invitation
- [x] 5.1 Add "Invite Portal User" action on `BuyerResource` (email, name)
- [x] 5.2 Create `InvitePortalUser` action/mailable with signed accept URL
- [x] 5.3 Create accept-invitation page (set password, verify email)
- [x] 5.4 Create `company_portal_users` record on successful acceptance
- [x] 5.5 Add portal users list relation manager or section on `BuyerResource`

### 6. Customer Portal — Authentication Pages
- [x] 6.1 Create `CustomerLogin` page (branded, link to staff login)
- [x] 6.2 Create `AcceptPortalInvitation` registration page
- [x] 6.3 Configure password reset and email verification for customer panel

### 7. Customer Portal — Request Submission (Manual)
- [x] 7.1 Create `CustomerRequestResource` with list page (scoped to buyer company)
- [x] 7.2 Create multi-step wizard or form: title, request type (goods/service), project (optional), required_by
- [x] 7.3 Add Repeater for manual items: description, quantity, unit of measure
- [x] 7.4 On submit: create `Request` + `RequestItem` records with `submission_method=manual`, `submitted_at=now()`
- [x] 7.5 Auto-set `buyer_id`, `team_id`, `submitted_by_user_id`; stage = `draft`

### 8. Customer Portal — Request Detail & Progress
- [x] 8.1 Create view page with sanitized request header (no internal notes)
- [x] 8.2 Create `CustomerRequestStagePresenter` for customer-friendly stage labels
- [x] 8.3 Display progress timeline component (stage history)
- [x] 8.4 Display customer-visible items list (description, qty, UOM — no article/supplier match info)
- [x] 8.5 Allow edit only while request is in `draft` and not yet advanced by staff

### 9. Admin — Portal Request Visibility
- [x] 9.1 Add "From Portal" badge/filter on `RequestResource` list
- [x] 9.2 Show `submission_method`, `submitted_at`, `submitted_by` on request view
- [x] 9.3 Send database/email notification to team when portal request submitted

### 10. Phase 1 Testing
- [x] 10.1 Feature test: portal user cannot access `app` panel
- [x] 10.2 Feature test: internal user without portal access cannot access `customer` panel
- [x] 10.3 Feature test: customer can only see own buyer company requests
- [x] 10.4 Feature test: manual request submission creates correct records
- [x] 10.5 Feature test: customer cannot see supplier quotes or internal notes
- [x] 10.6 Feature test: invite flow creates portal access
- [x] 10.7 Architecture test: `CustomerPanelProvider` registered, panel path correct (covered by CustomerPortalTest panel-path + registration checks)

---

## Phase 2 — Document Upload, Quote Actions, Notifications

### 11. Document Upload Submission
- [x] 11.1 Add submission method choice (manual vs document) on create form
- [x] 11.2 Upload RFQ/PR documents to `attachments` media collection
- [x] 11.3 Show attachments and submission method on customer request view
- [x] 11.4 Highlight document submissions in admin portal notification

### 12. Customer Quote Actions
- [x] 12.1 Extend `BuyerQuotePolicy` with customer panel `respond` and `uploadPo` abilities
- [x] 12.2 Create customer `BuyerQuotesRelationManager` (list sent quotes, accept/reject, upload PO)
- [x] 12.3 Auto-accept quote when PO is uploaded

### 13. Customer Notifications
- [x] 13.1 Create `NotifyPortalUsers` action
- [x] 13.2 Notify portal users on request stage change (`RequestObserver`)
- [x] 13.3 Notify portal users when buyer quote is sent (`BuyerQuoteObserver`)
- [x] 13.4 Notify portal users on shipment status change (`ShipmentObserver`)

### 14. Phase 2 Testing
- [x] 14.1 Feature test: document-based portal request with attachments
- [x] 14.2 Feature test: customer can accept sent buyer quote (policy)
- [x] 14.3 Feature test: customer cannot view draft buyer quotes
- [x] 14.4 Feature test: portal users notified on request stage change

---

---

## Phase 3 — Shipment Visibility, Branding, Dashboard

### 15. Shipment / DO Visibility
- [x] 15.1 Extend `ShipmentPolicy` for customer panel (outbound only, buyer-scoped)
- [x] 15.2 Create customer `ShipmentsRelationManager` on request view (tracking fields, DO number)
- [x] 15.3 Hide inbound shipments and internal notes from customer views

### 16. Team Branding on Portal
- [x] 16.1 Apply team logo and favicon from `PortalContext` team in `CustomerPanelProvider`
- [x] 16.2 Display buyer company name as brand when logged in
- [x] 16.3 Fallback to default branding on login page

### 17. Dashboard & Company Switcher
- [x] 17.1 Create `CustomerDashboard` with overview widgets
- [x] 17.2 `PortalRequestsOverviewWidget` — active, awaiting confirmation, fulfillment, completed stats
- [x] 17.3 `PortalActiveShipmentsWidget` — in transit, pending, delivered counts
- [x] 17.4 `PortalRecentRequestsWidget` — recent requests table
- [x] 17.5 Company switcher in user menu when user has multiple portal companies
- [x] 17.6 Enable database notifications on customer panel

### 18. Phase 3 Testing
- [x] 18.1 Feature test: customer can view outbound shipments only
- [x] 18.2 Feature test: customer dashboard loads with scoped stats
- [x] 18.3 Feature test: portal branding resolves team favicon/logo
- [x] 18.4 Feature test: company switcher changes portal context