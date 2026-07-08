# Tasks: unify-portal-membership-lifecycle

> Each task ends with both portal suites green. Access-check behavior is pinned by existing tests before any schema change lands.

## 1. Schema + state (UL-1, UL-2)

- [x] 1.1 Migration: `company_portal_users.user_id` nullable, add nullable `invited_name`/`invited_email`. Query audit result: every access path binds `user_id = <id>` (never matches NULL) and every company-side loop (`NotifyPortalUsers`, `AnnounceRfqOutcomes`) filters `is_active = true` — Invited rows (inactive, no user) are invisible to both by construction. Backfill folded into the same migration (schema + data are one atomic story here).
- [x] 1.2 `PortalMembershipState` enum (Invited/Active/Deactivated) + derived `state()` method on `CompanyPortalUser` (never stored, cannot drift); derivation covered in `tests/Feature/Portal/PortalMembershipLifecycleTest.php`
- [x] 1.3 Pinned by test: an Invited-state row grants no panel access on either portal

## 2. Writers converge (UL-1, UL-4, UL-5)

- [x] 2.1 `InvitePortalUser` creates/refreshes the Invited membership row in the same transaction as the invitation (TDD)
- [x] 2.2 `AcceptPortalInvitation` links the user and activates the existing Invited row — no duplicate; falls back to the old create path for pre-lifecycle invitations; credential-protection tests unchanged and green
- [x] 2.3 `ApprovePortalRegistration` regression-pinned green (creates Active rows directly; appears in the list identically)
- [x] 2.4 Backfill: Invited rows for pending invitations, duplicate-guarded (part of the 1.1 migration)

## 3. One list (UL-3)

- [x] 3.1 `PortalUsersRelationManager` reworked: three states with badge, name/email fall back to invited_name/invited_email; actions revoke + resend (Invited), deactivate (Active), reactivate (Deactivated), all `->authorize()`-wired; portal type derived from the mounting page class
- [x] 3.2 Registered on `ViewSupplier` (same class, real namespace, per the AcceptanceReportsRelationManager precedent); `PortalInvitationsRelationManager` and its tests deleted (superseded); supplier-side listing + portal-scoping test added
- [x] 3.3 `CompanyPortalUserPolicy::delete()` restricted to Invited rows (revoke only); update() covers deactivate/reactivate; `PortalInvitationPolicy` retained for the invitation records themselves

## 4. Validation

- [x] 4.1 pint clean; PHPStan zero errors on all touched files; 145 portal-adjacent tests + full suite green
- [x] 4.2 `openspec validate unify-portal-membership-lifecycle --strict` passes
