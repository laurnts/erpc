# Design: Consolidate team member management

## Context

The sidebar `MemberResource` was added by the team (`2fa8fe9`) on top of the Relaticle/Jetstream original (Edit Team page) and subsequently became load-bearing: it is the only UI for `is_approver` and post-invite `central_purchasing_role` editing, hosts the Key Account → Buyers relation manager, and receives deep links from Profit & Loss and Quotation Evaluation pages (8 call sites). The Relaticle components were never removed, leaving two invite forms, two pending-invitation lists, and two member lists with different write behavior.

Verified facts this design rests on (three-agent code audit, 2026-07-04):

- `TeamPolicy` gates (`addTeamMember`, `updateTeamMember`, `removeTeamMember`) are owner-only; `MembershipPolicy::delete` is owner-only AND forbids self-deletion. `App\Actions\Jetstream\RemoveTeamMember::authorize()` separately permits self-removal, and `ensureUserDoesNotOwnTeam()` blocks the owner.
- Jetstream's `Team::removeUser()` nulls the leaver's `current_team_id` without reassigning.
- The team owner has no `Membership` pivot row (Jetstream design) — invisible in both existing lists.
- Vendor `TeamInvitationController` passes only `role` to `AddsTeamMembers::add()`; the invitation row (which does carry `central_purchasing_role`) is deleted after `add()` returns.
- All current `is_approver` readers co-check `role`/sub-role, so stale flags are masked today, but `2026_01_29_043736_migrate_central_purchasing_people_to_team_members.php` wrote pivots directly and Edit Team's role modal writes only `role` — both produce inconsistent rows.
- `tests/Feature/Teams/*` exercise the vendor `TeamMemberManager` component; `resources/views/teams/team-member-manager.blade.php` is therefore NOT dead. `teams/show.blade.php` and `teams/create.blade.php` ARE dead (`Jetstream::ignoreRoutes()`).

## Goals / Non-Goals

- Goals: one member-management surface; spec-conformant role updates; invitation sub-role preserved; owner visible; leave-team preserved; global credentials out of team-admin reach; dead code removed.
- Non-Goals: changing Jetstream's owner-has-no-membership model; making owners linkable from P&L/Quotation Evaluation deep links (pre-existing gap); touching vendor-alias blades (`create-team-form`, `update-team-name-form`, `delete-team-form`, `team-member-manager`); localizing MemberResource's hardcoded English strings.

## Decisions

- **Keep the sidebar resource, strip Edit Team** — the ERP features and deep links already live on `MemberResource`; the reverse direction would mean porting all of them into generic Jetstream components.
- **Owner card, not a synthetic table row** — union-querying `Membership` rows plus a fake owner row breaks Filament record actions/sorting. The list page already has a custom blade; render an Owner card above the table. The owner needs no row actions (cannot be edited or removed).
- **Leave team = row action gated on `auth()->id() === $record->user_id`**, calling `RemoveTeamMember` directly. It must NOT use the `removeTeamMember` gate or `MembershipPolicy::delete` (both owner-only; delete forbids self). Redirect to `Filament::getHomeUrl()` afterward because `current_team_id` becomes null.
- **`App\Actions\Teams\UpdateTeamMemberRole` (single `execute()`, per decision guide)** owns role + `central_purchasing_role` + `is_approver` writes and the `TeamMemberUpdated` event. Cleanup semantics (extracted from `ViewMember`, the reference implementation): non-`central_purchasing` role clears both fields; non-Finance sub-role clears `is_approver`.
- **Invitation sub-role recovery inside `App\Actions\Jetstream\AddTeamMember`** — look up the pending `TeamInvitation` by team + email when no explicit sub-role is passed (the vendor controller deletes the invitation only after `add()` returns). Avoids overriding vendor routes/controllers.
- **Credential restriction** — remove email and password fields from the ViewMember edit slide-over; keep name and photo. Email/password belong to the user's own profile page.

## Risks / Trade-offs

- Removing Edit Team's members list removes the only Leave-team button → mitigated by shipping the row action in the same change (ordered before the removal in tasks).
- New-member flows lose automated coverage entirely if only vendor-component tests remain → new Pest tests target the `MemberResource` path and the new action.
- Cleanup migration mutates production pivot data → it only nulls values that are unreachable garbage under the guards all readers already apply; scoped WHERE clauses, no deletes.

## Migration Plan

1. Land shared action + invitation fix + sidebar additions (leave, owner card, restricted edit) with tests.
2. Remove Edit Team member sections and delete dead components/views/lang keys.
3. Run pivot cleanup migration.
Rollback: revert commits; migration is idempotent and only nulls inconsistent fields, so no reverse migration is provided (data was already semantically null).

## Open Questions

- None blocking. (Pending invitation to the owner's own email in dev data is data noise, not code.)
