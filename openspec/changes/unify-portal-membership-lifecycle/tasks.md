# Tasks: unify-portal-membership-lifecycle

> Each task ends with both portal suites green. Access-check behavior is pinned by existing tests before any schema change lands.

## 1. Schema + state (UL-1, UL-2)

- [ ] 1.1 Migration: `company_portal_users.user_id` nullable, add nullable `invited_name`/`invited_email`; audit every membership query for inner-join/user-not-null assumptions first (User::activeCustomer/SupplierPortalMembershipsQuery, PortalContextCore, policies, widgets)
- [ ] 1.2 `PortalMembershipState` enum (Invited/Active/Deactivated) + derived `state` accessor on `CompanyPortalUser`; unit tests for all three derivations
- [ ] 1.3 Pin by test: an Invited row grants no panel access on either portal

## 2. Writers converge (UL-1, UL-4, UL-5)

- [ ] 2.1 `InvitePortalUser` creates/refreshes the Invited membership row with the invitation; revoke deletes both (TDD)
- [ ] 2.2 `AcceptPortalInvitation` fills `user_id`, activates the row, keeps credential protection for existing users (existing tests must stay green unchanged)
- [ ] 2.3 `ApprovePortalRegistration` verified to produce the same row shape (Active state) — regression-pinned
- [ ] 2.4 Backfill migration: Invited rows for pending invitations; idempotent, tested via the migration-file pattern

## 3. One list (UL-3)

- [ ] 3.1 Rework `PortalUsersRelationManager`: all three states with badge column; actions revoke+resend (Invited), deactivate (Active), reactivate (Deactivated); portal type parameterized
- [ ] 3.2 Register on `SupplierResource`; delete `PortalInvitationsRelationManager` and its tests (superseded); supplier-side listing test
- [ ] 3.3 Update `PortalInvitationPolicy`/`CompanyPortalUserPolicy` for the new transitions

## 4. Validation

- [ ] 4.1 pint; PHPStan clean on touched files; both portal suites + full suite green
- [ ] 4.2 `openspec validate unify-portal-membership-lifecycle --strict`
