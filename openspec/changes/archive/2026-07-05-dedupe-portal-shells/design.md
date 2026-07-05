# Design: dedupe-portal-shells

## Context

Two portals share one architecture but ~900 lines are duplicated across four clone pairs (panel providers 300, accept pages 331, login pages 150, invite actions 124). The composition layer already exists (`PortalContextCore` + facades, `PortalPanelConfigurator`, `UsePanelSession`, `ResolvesPanelContext`); this change moves the cloned material into it. The prior refactor's rule — **composition only, no inheritance, no panel factory** — is kept.

## Decision 1: Parameterize the configurator, don't grow a base provider

`PortalPanelConfigurator::apply()` currently owns middleware/colors/theme. It gains the branding resolvers and the switch-company action via explicit parameters:

```php
PortalPanelConfigurator::apply(
    $panel,
    context: SupplierPortalContext::class,   // resolved from the container per request
    dashboard: SupplierDashboard::class,
    switchLabel: 'Switch Company',
);
```

The two context facades stay distinct classes (they bind different guards/session prefixes), so the configurator types against a small `PortalContext` interface (`company()`, `companies()`, `setCompany()`) that both facades already satisfy structurally — extracting the interface is part of DP-1. Alternative considered: a `PortalPanelDefinition` value object; rejected as ceremony for three parameters.

## Decision 2: Extract the accept *transaction*, keep the accept *pages* per panel

The `accept()` DB transaction is byte-identical and fully derivable from `PortalInvitation` (which carries `portal`), so it becomes `app/Actions/Portal/AcceptPortalInvitation::execute(PortalInvitation $invitation, string $name, string $email, string $password): User`. The Filament pages stay as thin per-panel classes because:

- panel page discovery is namespace-scoped per panel; sharing one class means manual registration in both panels plus panel-conditional headings — more coupling than the ~60 lines it saves;
- the per-panel `PortalType` token filter reads clearest at the page's own query;
- `$withoutRouteMiddleware`/slug/view wiring differing by panel would turn into conditionals.

Same reasoning keeps the login pages per panel; their only real duplication (heading strings + form tweak) is not worth a shared abstraction. If a third portal ever appears, revisit.

## Decision 3: One invite action + one mail, keyed by `PortalType`

`InvitePortalUser::execute(Team $team, Company $company, PortalType $portal, string $email, string $name, User $invitedBy)`. The portal drives: the company-role guard (`is_buyer` for customer, `is_supplier` for supplier), the stale-invitation delete scope, the accept URL (`getCustomerPortalUrl`/`getSupplierPortalUrl`), and the mail copy. `PortalUserInvitationMail` reads `$invitation->portal` for subject and view strings; the supplier mail class and duplicate Blade view are deleted. Call sites (staff `ViewSupplier` action, buyer `PortalUsersRelationManager`) pass their portal explicitly. Alternative considered: keep two thin facade actions over a shared core (mirroring `PortalContextCore`); rejected — unlike the context services, the invite action has no guard/session state to bind, so a parameter is enough.

## Decision 4: Rename `hasActiveBuyerPortalAccess()` now, not later

CP-1 renamed the context/service side to "Customer" but left the `User` gate method on "Buyer"; Slice 2 then added `hasActiveSupplierPortalAccess()`, cementing two vocabularies in adjacent methods. Mechanical rename with no semantic change; done in this change because every touched call site (panel providers, middleware, policies) is already in this change's blast radius.

## Risks / rollout

- The dedup must not change observable behavior; the existing portal feature tests are the harness. Sequence: land shared pieces alongside clones, switch call sites, delete clones — each task keeps the suite green.
- Coordination: `add-public-product-catalog` (uncommitted working tree) touches `app/Actions/CustomerPortal/*` and portal views. This change starts only after that change is committed and archived.
