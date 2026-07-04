# Design: Retire Company Resource

## Context

`Company` is a single Eloquent model with `is_buyer`/`is_supplier` boolean flags. Three Filament resources sit on top of it: `CompanyResource` (unfiltered, Workspace group), `BuyerResource` (`where is_buyer`, Master Data), `SupplierResource` (`where is_supplier`, Master Data). Buyer/Supplier already expose the full company form via slide-over edit; only the role flags (hidden, force-set) and credit fields (read-only by design) differ. `CompanyResource` uniquely provides role toggling, access to role-less companies, and serves as the login landing page.

## Goals / Non-Goals

- **Goals:** one editing surface per role, no role-less companies possible, no dead parallel form schema, Workspace group removed.
- **Non-Goals:** schema changes, changes to the credit-limit approval workflow, removing Opportunities (kept as-is per decision), changes to the customer portal.

## Decisions

### 1. Delete `CompanyResource` rather than hide it

`shouldRegisterNavigation = false` would be cheaper but leaves a hidden parallel edit surface that will drift from the Buyer/Supplier forms. Deleting removes the drift risk; the ~10 reference sites are retargeted explicitly.

### 2. Shared form schema as a standalone class

`CompanyResource::getFormSchema()` currently backs both the Company form and People's inline company create. It moves to a dedicated schema class (per project convention, e.g. `app/Filament/Resources/BuyerResource/Schemas/` or a shared `Companies` schema namespace — final location follows existing sibling patterns at implementation time). `BuyerResource`, `SupplierResource`, and `PeopleResource` all consume it, preserving the inline-form-consistency guarantee.

### 3. Role invariant enforced at the form layer

Every creation path sets at least one role:
- BuyerResource create: `is_buyer = true` forced (existing behavior) + optional "Also a supplier" checkbox.
- SupplierResource create: `is_supplier = true` forced + optional "Also a buyer" checkbox.
- People inline create: visible buyer/supplier checkboxes, validation requires at least one.

No database constraint is added; the invariant is a UI/validation concern, and existing role-less rows are cleaned up as data work. Dual-role companies appear in both lists — this is expected and correct (same record, two role views).

### 4. Landing page → Buyers

`LoginResponse` and the panel `homeUrl` currently target the Companies index. Both retarget to the Buyers index (first Master Data entry). If a dashboard is introduced later it can take over; out of scope here.

### 5. Record links pick the role view

Places that link to a company record (People view, Opportunity view, relation managers) link to the Buyer view when `is_buyer` is set, otherwise the Supplier view. After the role invariant, one of the two always exists.

### 6. Exporter

`CompanyExporter` remains as the export backing for the Buyer/Supplier list export actions if already wired that way; otherwise the export action moves onto those lists. Resolved during implementation — the exporter operates on `Company` and is view-agnostic.

### 7. OnboardSeed fixtures

Demo company fixtures gain a role flag (e.g. `is_buyer: true`) so onboarding never seeds unreachable companies. Existing role-less demo rows (Airbnb, Apple, Figma, Notion — no people, no transactions) are deleted via a data migration/command; linked demo notes/tasks/opportunities from the same fixture set are removed with them via existing cascade/polymorphic cleanup, verified in the task.

## Risks / Trade-offs

- **Dual-role confusion:** the same company appearing in Buyers and Suppliers may surprise users; mitigated by the explicit "Also a …" checkbox naming.
- **Deep links:** bookmarked `/companies/...` URLs 404 after deletion. Accepted — internal tool, low traffic.
- **Fixture cleanup cascade:** demo opportunities/notes reference demo companies; deletion order in the cleanup must respect FKs (companies cascade to pivots; polymorphic noteables need explicit cleanup). Covered by a test.
