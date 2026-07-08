# Change: Standardize buyer-portal naming and per-panel sessions

## Why
The buyer portal is called two different things: users see `/buyer` in the URL, but
the code, config, auth guard, panel id, and the `customer-portal` capability all say
"customer". This split makes the codebase harder to reason about and search. At the
same time, the three panels (staff, buyer, supplier) use inconsistent session-cookie
naming — staff silently rides Laravel's default `APP_NAME`-derived cookie while buyer
and supplier have explicit names — which obscures the one feature the team relies on:
logging into all three panels in a single browser across multiple tabs.

This change makes the terminology consistent (customer → buyer everywhere) and gives
every panel an explicit, isolated session cookie so concurrent multi-tab login is a
documented, predictable contract.

## What Changes
- **BREAKING (config/deploy):** Rename the capability `customer-portal` → `buyer-portal`.
- **BREAKING (config/deploy):** Rename config + env keys: `customer_path`→`buyer_path`,
  `customer_domain`→`buyer_domain`, `customer_session_cookie`→`buyer_session_cookie`,
  `customer_portal_enabled`→`buyer_portal_enabled`; env `CUSTOMER_*`→`BUYER_*`. Defaults
  are preserved (path stays `buyer`, enabled stays `true`) so local/dev needs no `.env` edits.
- **BREAKING (auth):** Rename the Filament panel id `customer`→`buyer` and the auth guard
  `customer`→`buyer`. Cascades to route names (`filament.customer.*`→`filament.buyer.*`)
  and generated CSS classes (`.fi-panel-customer`/`.fi-customer-login`).
- **BREAKING (data):** Rename `PortalType::Customer = 'customer'`→`PortalType::Buyer = 'buyer'`
  and migrate the stored value in `portal_invitations.portal` and `company_portal_users.portal`
  from `customer`→`buyer` (with column-default update). Reversible.
- Rename PHP namespaces, directories, and classes: `App\Filament\Customer`→`App\Filament\Buyer`,
  `App\Actions\CustomerPortal`→`App\Actions\BuyerPortal`, `App\Services\CustomerPortal`→
  `App\Services\BuyerPortal`, `CustomerPanelProvider`→`BuyerPanelProvider`,
  `CustomerRequestResource`→`BuyerRequestResource`, `CustomerPortalContext`→`BuyerPortalContext`,
  `Customer*` middleware/pages/widgets → `Buyer*`, plus the `resources/views/filament/customer`
  view namespace → `buyer`.
- **Standardize session cookies:** staff = `erpc_staff_session` (newly explicit),
  buyer = `erpc_buyer_session`, supplier = `erpc_supplier_session` (unchanged). Each panel
  keeps an isolated session so staff/buyer/supplier tabs coexist in one browser.
- **Preserve** genuine business copy where "customer" is the correct word (e.g. "Customer
  support contact", marketing "customer relationships", the "Buyer (Customer)" clarifier labels).

## Impact
- Affected specs: `buyer-portal` (new, renamed from `customer-portal`), `customer-portal`
  (removed), `authentication` (portal-auth requirement renamed + concurrent-sessions requirement added).
- Affected code: `config/app.php`, `config/auth.php`, `config/session.php`, `app/Providers/Filament/*`,
  `app/Filament/Customer/**`, `app/Actions/CustomerPortal/**`, `app/Services/CustomerPortal/**`,
  `app/Services/Portal/CustomerPortalContext.php`, `app/Http/Middleware/*CustomerPortal*` and
  `UsePanelSession`, `app/Enums/PortalType.php`, `app/Support/PanelDomain.php`,
  `app/Support/PortalPanelConfigurator.php`, `app/Support/ActivityLogContext.php`,
  `app/Http/Responses/LoginResponse.php`, `app/Livewire/Catalog/*`, `resources/views/filament/customer/**`,
  `resources/css/filament/app/theme.css`, plus a new data migration and ~24 test files.
- Deploy: server `.env` must rename `CUSTOMER_*`→`BUYER_*`; the cookie rename logs out active
  staff and buyer sessions once (re-login only); the data migration must run on deploy.
