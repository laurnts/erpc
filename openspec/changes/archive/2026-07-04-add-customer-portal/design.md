# Design: Customer Portal

## Context

ERPC is a multi-tenant B2B trading platform. Internal users (CP, Sales, Finance) work in the `app` Filament panel on subdomain `app.{domain}`. Buyers exist as `Company` records (`is_buyer = true`) with contacts in `People` via `company_people` pivot — but contacts do not have login accounts today.

The original ERP design explicitly deferred the customer portal. The `Request` model, stage workflow, `RequestItem`, and Spatie Media Library `attachments` collection are ready to reuse.

**Stakeholders**: Buyer contacts (portal users), Key Account / CP staff (review submissions), admins (invite and manage access).

## Goals

- Enable buyer self-service request submission without WhatsApp/email dependency
- Provide read-only progress tracking with customer-friendly stage labels
- Reuse existing `Request` entity — single source of truth for admin and customer views
- Enforce supplier confidentiality (buyer never sees supplier info, margins, or internal approvals)
- Use path-based URL: `{APP_URL}/customer/login` (same pattern as `sysadmin` panel)
- Invite-only onboarding (no open public registration to arbitrary buyers)

## Non-Goals

- Supplier portal (suppliers responding to quotes via portal) — out of scope
- Replacing email delivery of formal quotes/orders (portal complements email)
- Public self-registration without admin invite
- Customer access to internal ERP modules (articles, suppliers, QE, PNL)
- Inventory or payment gateway integration
- Parsing uploaded RFQ documents automatically (OCR/AI) — staff reviews uploads manually

## Decisions

### Decision 1: Separate Filament Panel (`customer`)

**What:** Register a new Filament panel with `->path(config('app.customer_path', 'customer'))`.

**Why:**
- Mirrors proven `sysadmin` path pattern (`SystemAdminPanelProvider`)
- No route conflict with `app` panel (uses subdomain `app.{domain}`)
- Isolated navigation, branding, and authorization
- Customer login at `/customer/login` as requested

**Alternatives considered:**
- Same `app` panel with buyer role: Rejected — exposes internal navigation groups and risks data leaks
- Subdomain `customer.{domain}`: Valid but not required; path is simpler for MVP

### Decision 2: Portal User Linkage via `company_portal_users` Pivot

**What:** New pivot table linking `users` to buyer `companies` with `team_id` scope.

```php
Schema::create('company_portal_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['company_id', 'user_id']);
});
```

**Why:**
- One user may access multiple buyer companies (rare but supported)
- Explicit team scoping for multi-tenant isolation
- Keeps internal Jetstream `team_user` separate from buyer portal access
- Optional link to `people_id` can be added later for CRM sync

**Alternatives considered:**
- Reuse `People` as auth entity: Rejected — People has no password/auth; would require new guard and duplicate identity
- Add `company_id` directly on `users`: Rejected — conflates internal staff and buyer users

### Decision 3: Shared `User` Model, Split Panel Access

**What:** Customer portal users are standard `User` records. `canAccessPanel()` returns true for exactly one panel type based on portal membership:

```php
public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'app' => $this->belongsToAnyInternalTeam(),
        'customer' => $this->hasActivePortalAccess(),
        default => false,
    };
}
```

**Why:**
- Reuses Jetstream password reset, email verification, 2FA infrastructure
- No duplicate user tables
- Internal staff and buyer contacts remain mutually exclusive by default

**Alternatives considered:**
- Separate `CustomerUser` model + guard (like `SystemAdministrator`): More isolation but duplicates auth plumbing; defer unless security audit requires it

### Decision 4: Reuse `Request` Model with Submission Metadata

**What:** Add fields to `requests` table:

| Column | Type | Purpose |
|--------|------|---------|
| `submission_method` | enum: `manual`, `document` | How customer submitted |
| `submitted_at` | timestamp nullable | When customer formally submitted |
| `submitted_by_user_id` | FK users nullable | Portal user who submitted |

Add `CreationSource::PORTAL` to `CreationSource` enum for `creator_id` / audit context.

Portal-created requests:
- `stage` = `draft` (staff reviews before advancing)
- `buyer_id` = customer's linked company (auto-set, not selectable)
- `team_id` = trading team from portal context

**Why:**
- No parallel request table
- Admin `RequestResource` unchanged except new fields and badge
- Existing workflow applies after staff accepts submission

### Decision 5: Customer-Facing Stage Labels (Sanitized View)

**What:** Map internal `RequestStage` to customer-visible labels via `CustomerRequestStagePresenter` — never expose internal stage names or supplier-related tabs.

| Internal Stage | Customer Label |
|----------------|----------------|
| `draft` (not yet reviewed) | Permintaan Diterima |
| `draft` (under staff review) | Sedang Direview |
| `awaiting_supplier_response` | Sedang Mencari Penawaran |
| `preparing_buyer_quote` | Penawaran Sedang Disiapkan |
| `awaiting_buyer_confirmation` | Menunggu Konfirmasi Anda |
| `preparing_supplier_order` … `delivered` | Diproses / Dikirim / Terkirim |
| `invoiced`, `paid`, `completed` | Selesai |
| `cancelled` | Dibatalkan |

**Why:** Internal stages are operational; customers need simplified status.

### Decision 6: Supplier Confidentiality Policy Layer

**What:** Dedicated `CustomerRequestPolicy` and customer Filament resources that:
- Scope queries: `buyer_id IN (user's portal companies)`
- Hide: `internal_notes`, supplier quotes, supplier orders, QE, PNL, margin fields
- Expose: request header, customer items (description/qty/UOM only), buyer quotes (customer price only), buyer orders, shipments (tracking only)

**Why:** Core ERP requirement — buyer never sees supplier info.

### Decision 7: Invite Flow from Admin Buyer Resource

**What:** Admin action on `BuyerResource`: "Invite Portal User" → email with signed registration/accept URL → creates `company_portal_users` record.

**Why:** Controlled onboarding; admin verifies buyer company before granting access.

### Decision 8: Tenancy Model for Portal

**What:** Portal resolves `team_id` from the user's portal company membership (not Jetstream team switcher). If user has access to one buyer company, auto-select. If multiple, show company switcher in portal header.

**Why:** Customers are not members of internal `Team` workspaces.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Customer Browser                                            │
│  https://erpc.com/customer/login                             │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│  CustomerPanelProvider (Filament)                            │
│  - CustomerLogin, CustomerRequestResource                    │
│  - CustomerRequestPolicy (buyer_id scope)                      │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│  Shared Domain Layer                                         │
│  Request, RequestItem, BuyerQuote, Media (attachments)       │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│  Admin Panel (app.erpc.com)                                  │
│  RequestResource — sees all requests including portal ones   │
│  Badge: "From Portal" on portal-submitted requests           │
└─────────────────────────────────────────────────────────────┘
```

## Database Schema Summary

### New: `company_portal_users`
See Decision 2.

### Modified: `requests`
- `submission_method` (nullable enum)
- `submitted_at` (nullable timestamp)
- `submitted_by_user_id` (nullable FK)

### Modified: `CreationSource` enum
- Add `PORTAL = 'portal'`

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Data leak to customer (supplier info) | Dedicated policy + feature tests; no reuse of admin RelationManagers |
| User is both staff and buyer | `canAccessPanel` enforces mutual exclusion; document exception process |
| Document upload without structured items | Request stays in draft; staff notified to parse and add items |
| Duplicate requests (portal + email) | Admin can merge/link manually; future dedup out of scope |
| Email invite abuse | Signed URLs with expiry; rate limiting on invite action |

## Migration Plan

1. Deploy migrations (`company_portal_users`, request fields) — non-breaking (nullable columns)
2. Deploy `CustomerPanelProvider` behind feature flag or env `CUSTOMER_PORTAL_ENABLED=true`
3. Admin invites pilot buyers
4. Monitor portal submissions; iterate Phase 2

No data migration required for existing requests (`submission_method` null = internal-created).

## Open Questions

1. **Language**: Portal UI in Indonesian, English, or follow team locale setting?
   - **Recommendation**: Indonesian for MVP (primary users); i18n-ready strings.

2. **Edit after submit**: Can customer edit request while still in `draft`?
   - **Recommendation**: Yes, until staff advances stage past draft.

3. **Multiple contacts per buyer**: All see all company requests or per-user scope?
   - **Recommendation**: All portal users for a buyer company see all that company's requests.
