# Tasks: Standardize buyer-portal naming and per-panel sessions

## 1. Config, env, and session cookies
- [x] 1.1 Rename config keys in `config/app.php`: `customer_path`→`buyer_path`, `customer_domain`→`buyer_domain`, `customer_session_cookie`→`buyer_session_cookie`, `customer_portal_enabled`→`buyer_portal_enabled`; env `CUSTOMER_*`→`BUYER_*` (keep defaults: path `buyer`, enabled `true`)
- [x] 1.2 Set the buyer cookie default to `erpc_buyer_session` and update the `panel_session_cookies` map key to the buyer path
- [x] 1.3 Set the staff default cookie explicitly: `SESSION_COOKIE=erpc_staff_session` (`config/session.php` default + `.env.example`)
- [x] 1.4 Update `.env.example` and any deployment docs with the renamed `BUYER_*` / `SESSION_COOKIE` keys

## 2. Auth guard
- [x] 2.1 Rename guard `customer`→`buyer` in `config/auth.php`
- [x] 2.2 Update every `auth('customer')`, `Auth::guard('customer')`, `->authGuard('customer')`, `request->user('customer')` to `buyer` (LoginResponse, QuoteCartPage, SubmitQuoteCart, AcceptPortalInvitation, ResolvesPanelContext, policies, blade)

## 3. Enum + data migration
- [x] 3.1 Rename `PortalType::Customer = 'customer'`→`PortalType::Buyer = 'buyer'` and its display label to "Buyer Portal"; update all `PortalType::Customer` references
- [x] 3.2 Create a migration updating `portal_invitations.portal` and `company_portal_users.portal` from `customer`→`buyer` and changing both column defaults to `buyer`; implement a reversible `down()`
- [x] 3.3 Update `ActivityLogContext` map key `customer`→`buyer` and `CreationSource::PORTAL` label to "Buyer Portal"

## 4. Namespaces, directories, classes (git mv)
- [x] 4.1 `app/Filament/Customer`→`app/Filament/Buyer`; rename `Customer*` resources/pages/widgets/schemas to `Buyer*`; update namespaces + discovery paths
- [x] 4.2 `app/Actions/CustomerPortal`→`app/Actions/BuyerPortal`; `app/Services/CustomerPortal`→`app/Services/BuyerPortal`
- [x] 4.3 `CustomerPanelProvider`→`BuyerPanelProvider`; register in `bootstrap/providers.php`
- [x] 4.4 `CustomerPortalContext`→`BuyerPortalContext`; `PanelDomain::customerHost()`→`buyerHost()`; middleware `InitializeCustomerPortalContext`/`EnsureCustomerPortalEnabled`→`Buyer*`
- [x] 4.5 `resources/views/filament/customer`→`resources/views/filament/buyer`; update view references

## 5. Panel id + cascading references
- [x] 5.1 `->id('customer')`→`->id('buyer')` on the buyer panel; `->path(config('app.buyer_path'))`
- [x] 5.2 Update all `getId() === 'customer'` / `getPanel('customer')` checks to `buyer`
- [x] 5.3 Update hardcoded route prefixes `filament.customer.*`→`filament.buyer.*` (`TimelineAudience`, redirects)
- [x] 5.4 Update CSS `.fi-panel-customer` / `.fi-customer-login`→`.fi-panel-buyer` / `.fi-buyer-login` in `theme.css`
- [x] 5.5 Update split-sidebar layout list `['app', 'customer']`→`['app', 'buyer']`

## 6. Preserve genuine business copy
- [x] 6.1 Audit remaining "customer" strings; leave "Customer support contact", "customer relationships" marketing copy, and "Buyer (Customer)" clarifier labels unchanged

## 7. Tests
- [x] 7.1 Rename and update the ~24 test files referencing customer panel/guard/config/enum (paths, guard names, `portal = buyer`, cookie names)
- [x] 7.2 Add/adjust a test asserting the three panels use distinct session cookies (`erpc_staff_session`, `erpc_buyer_session`, `erpc_supplier_session`) and that a buyer request does not clobber the staff session

## 8. Verify
- [x] 8.1 `vendor/bin/pint` (Pint) and `composer lint` (Rector) clean
- [x] 8.2 PHPStan: no rename-introduced errors (no dangling refs); project's pre-existing baseline errors unchanged by this rename
- [x] 8.3 Run buyer-portal, supplier-portal, authentication, and catalog test suites green (1,815 tests pass; pre-existing ArchTest debt separately resolved)
- [x] 8.4 Staff + buyer + supplier session isolation verified programmatically in UsePanelSessionTest (distinct erpc_staff/buyer/supplier_session cookies; staff fall-through)
