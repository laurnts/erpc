# Change: Unify portal membership into one state-driven lifecycle

## Why

A portal person's staff-facing record is split across two entities: `portal_invitations` before acceptance and `company_portal_users` after. The split produced a real support incident: an admin invited a contact, saw no record anywhere (the Portal Users tab lists memberships only), and could not tell whether the invite happened. The interim fix (`d666736`, `8ea23fd`) added a pending-only invitations tab, but the product owner's direction is one **Portal Users** list where invited/active/deactivated are states of a single row — no second tab, no duplicate rows, and revoke/deactivate/re-invite as state transitions in one place.

## What Changes

- **UL-1 Membership row from invite onward**: `company_portal_users` gains nullable `invited_name`/`invited_email` columns and makes `user_id` nullable. `InvitePortalUser` creates (or refreshes) the membership row in the *Invited* state alongside the invitation (which remains the token/acceptance-URL carrier). Acceptance fills `user_id` and activates the row instead of creating one; revoking a pending invite deletes both.
- **UL-2 Derived state, no status column**: a `PortalMembershipState` enum derived per row — `user_id` null → *Invited*; user set + `is_active` → *Active*; user set + `!is_active` → *Deactivated*. Access checks are untouched by construction: every existing gate already requires `is_active = true` **and** a user match, which an Invited row (inactive, no user) can never satisfy.
- **UL-3 One Portal Users list**: `PortalUsersRelationManager` shows all three states with per-state actions — *Invited*: revoke, resend; *Active*: deactivate; *Deactivated*: reactivate. The pending-invitations tab from `8ea23fd` is deleted. The same relation manager is registered on `SupplierResource` (parameterized by portal type), closing the supplier-side gap where staff currently cannot see portal users at all.
- **UL-4 All membership writers converge**: `AcceptPortalInvitation` updates the Invited row; `ApprovePortalRegistration` (catalog self-registration) creates the row directly in *Active* state as today — registration-sourced members appear in the list identically.
- **UL-5 Backfill**: pending invitations get Invited membership rows; orphaned accepted invitations (none known) are ignored.

## Impact

- Affected specs: `customer-portal` (MODIFIED: Customer Portal User Access — invited state visible and revocable in one list), `supplier-portal` (MODIFIED: Supplier Portal Access via Invitations — staff list of supplier portal users/invitations)
- Affected code: `company_portal_users` migration (nullable `user_id`, invited columns), `app/Models/CompanyPortalUser.php` (+ state accessor), `app/Enums/PortalMembershipState.php` (new), `app/Actions/Portal/{InvitePortalUser,AcceptPortalInvitation}.php`, `app/Actions/CustomerPortal/ApprovePortalRegistration.php`, `app/Filament/Resources/BuyerResource/RelationManagers/PortalUsersRelationManager.php` (reworked, shared), `SupplierResource` registration, deletion of `PortalInvitationsRelationManager`
- Tests: portal suites must stay green (access-check invariants pinned before the schema change lands); new state-transition tests per action
- Risk note: `user_id` nullable on an auth-adjacent table — every query that joins users through memberships must be audited for inner-join assumptions before the migration ships
