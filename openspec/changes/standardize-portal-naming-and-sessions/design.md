# Design: Standardize buyer-portal naming and per-panel sessions

## Context
"customer" is not one string but five independent identifier systems, only one of which
touches stored data. Treating them uniformly (a blind find-replace) would both corrupt
genuine business copy and silently break the `portal` column. This design records how each
system is renamed and in what order, so the change lands atomically.

## The five identifier systems

| System | Today | After | Notes |
|---|---|---|---|
| PHP namespaces / dirs / classes | `App\Filament\Customer`, `CustomerPanelProvider`, … | `App\Filament\Buyer`, `BuyerPanelProvider`, … | Mechanical; `git mv` to preserve history |
| Filament panel id | `->id('customer')` | `->id('buyer')` | Auto-generates route names + CSS classes |
| Auth guard | `config/auth.php` guard `customer`; `auth('customer')` | guard `buyer`; `auth('buyer')` | Independent of panel id but both were `customer` |
| Config / env keys | `customer_path`, `CUSTOMER_*` | `buyer_path`, `BUYER_*` | Defaults preserved |
| Stored enum value | `PortalType::Customer = 'customer'` | `PortalType::Buyer = 'buyer'` | Requires data migration |

## Key decisions

### 1. Rename the capability, don't alias it
The `customer-portal` OpenSpec capability becomes `buyer-portal`. Keeping the old folder
name while the code says "buyer" would reintroduce the exact split this change removes.
Modeled as: all requirements ADDED under `buyer-portal`, all requirements REMOVED from
`customer-portal` (reason: renamed).

### 2. Enum value migration is the only data risk
`PortalType` backs the `portal` column on `portal_invitations` and `company_portal_users`.
Changing only the PHP case name (`case Buyer = 'customer'`) would leave an ugly, misleading
mapping. Instead we change both name and value and ship a data migration:

```
UPDATE portal_invitations   SET portal = 'buyer' WHERE portal = 'customer';
UPDATE company_portal_users SET portal = 'buyer' WHERE portal = 'customer';
-- column default: 'customer' -> 'buyer'
```

`down()` reverses both the data and the default. Existing supplier rows (`portal = 'supplier'`)
are untouched.

### 3. Session cookies: make staff explicit, keep the fall-through model
`UsePanelSession` swaps `session.cookie` per request by path prefix. Buyer and supplier
already have explicit cookies; staff is the fall-through default. We make staff explicit by
setting the default `SESSION_COOKIE=erpc_staff_session`, so:

- staff (`app` panel, root path) → `erpc_staff_session` (default, no override)
- buyer (`/buyer`) → `erpc_buyer_session` (override in `panel_session_cookies` map)
- supplier (`/supplier`) → `erpc_supplier_session` (unchanged)

Distinct cookie names on the same host = three independent sessions, so the team can hold
staff + buyer + supplier logins in three tabs of one browser. The middleware's Livewire
handling (referer, then snapshot `memo.path`) is unchanged — only the buyer cookie name and
map key move from `customer` to `buyer`.

### 4. Preserve genuine "customer" copy
An audit pass keeps these as-is (they are not the portal name):
- `ContactRole::SUPPORT => 'Customer support contact'`
- marketing copy "customer relationships" (`resources/views/home/...`)
- clarifier labels "This company is a Buyer (Customer)" / "Also a Buyer (Customer)"

`CreationSource::PORTAL` and `PortalType` display labels ("Customer Portal") DO change to
"Buyer Portal" since they name the portal.

## Ordering (atomicity)
The panel 500s if identifiers are half-renamed, so the implementation proceeds in one branch:
config/guard → enum + migration → session cookies → namespaces/dirs/classes → panel id +
cascading route/CSS/layout refs → views → copy audit → tests → verify. No intermediate commit
is expected to boot a mixed state.

## Deploy notes
- Rename `CUSTOMER_*`→`BUYER_*` in every server `.env` at deploy time or the portal path/domain
  resolve to defaults unexpectedly.
- The cookie rename (buyer + staff) invalidates active sessions once; users simply re-login.
- Run the data migration as part of the deploy; it is idempotent and reversible.

## Risks / trade-offs
- Wide blast radius (~100 files) — mitigated by static analysis (PHPStan), Pint, and the
  existing portal/buyer test suites gating the merge.
- One-time forced re-login for staff and buyers — acceptable and communicated.
