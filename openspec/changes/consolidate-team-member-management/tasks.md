# Tasks: Consolidate team member management

Ordered so member capabilities land on the Team Members page BEFORE the duplicate UI is removed (no functionality gap at any point).

## 1. Shared role-update action + invitation fix

- [x] 1.1 Create `app/Actions/Teams/UpdateTeamMemberRole.php` (`final` class, single `execute()`): writes `role`, sets/clears `central_purchasing_role` and `is_approver` (non-`central_purchasing` clears both; non-Finance sub-role clears `is_approver`), dispatches `TeamMemberUpdated`. Port logic from `ViewMember.php:134-166`.
- [x] 1.2 Pest tests for the action: promote to CP+Finance+approver; demote to editor clears both; Finance→Key Account clears approver.
- [x] 1.3 Refactor `ViewMember` edit action to call `UpdateTeamMemberRole` (delete inline pivot logic).
- [x] 1.4 Fix `app/Actions/Jetstream/AddTeamMember.php`: when `$centralPurchasingRole` is null and role is `central_purchasing`, recover the sub-role from the pending `TeamInvitation` (team + email lookup — the vendor controller deletes it only after `add()` returns).
- [x] 1.5 Pest test: accepting an invitation with a CP sub-role produces a membership carrying that sub-role.

## 2. Team Members page gains missing capabilities

- [x] 2.1 Add "Leave team" row action to `MemberResource` table: visible only when `auth()->id() === $record->user_id`, requires confirmation, calls `App\Actions\Jetstream\RemoveTeamMember`, redirects to `Filament::getHomeUrl()`. Do NOT gate on `removeTeamMember`/`MembershipPolicy::delete` (owner-only; delete forbids self).
- [x] 2.2 Pest tests: member can leave; owner gets validation error ("may not leave a team you created"); leave action not visible on other members' rows.
- [x] 2.3 Add Owner card above the table in `resources/views/filament/resources/member-resource/pages/list-members.blade.php` (avatar, name, email, Owner badge; no actions). Expose owner via a method on `ListMembers`.
- [x] 2.4 Pest test: owner appears on the list page; owner has no Membership row.
- [x] 2.5 Remove email and password fields from `ViewMember` edit slide-over (keep name, photo, role fields).
- [x] 2.6 Pest test: member edit updates role/profile basics; user's email and password hash are unchanged after edit.

## 3. Remove the duplicate Relaticle member UI

- [x] 3.1 Edit `app/Filament/Pages/EditTeam.php`: remove imports (L7, L10) and the `AddTeamMember`, `PendingTeamInvitations`, `TeamMembers` entries from `form()` (keep `UpdateTeamName`, `UpdateTeamCompanyInfo`, `UpdateTeamBranding`, `DeleteTeam`).
- [x] 3.2 Delete `app/Livewire/App/Teams/AddTeamMember.php`, `app/Livewire/App/Teams/TeamMembers.php`, `resources/views/livewire/app/teams/add-team-member.blade.php`, `resources/views/livewire/app/teams/team-members.blade.php`. KEEP `PendingTeamInvitations.php` (embedded by `list-members.blade.php`). Do NOT touch `App\Actions\Jetstream\AddTeamMember` (different class, registered in `JetstreamServiceProvider`).
- [x] 3.3 Delete dead legacy blades `resources/views/teams/show.blade.php` and `resources/views/teams/create.blade.php`. KEEP `teams/team-member-manager.blade.php`, `teams/create-team-form.blade.php`, `teams/update-team-name-form.blade.php`, `teams/delete-team-form.blade.php` (vendor Livewire aliases; `tests/Feature/Teams/*` render them).
- [x] 3.4 Prune orphaned keys from `lang/en/teams.php`: `form.email`, `sections.add_team_member`, `sections.team_members`, `actions.add_team_member`, `actions.update_team_role`, `actions.remove_team_member`, `actions.leave_team`, `notifications.team_member_removed`, `notifications.leave_team`, `notifications.permission_denied.cannot_update_team_member`, `notifications.permission_denied.cannot_leave_team`, `notifications.permission_denied.cannot_remove_team_member`, `modals.leave_team`. KEEP `actions.save`, `notifications.team_invitation_sent`, and all `update_team_name`/`pending_team_invitations`/`delete_team`/`edit_team` keys.
- [x] 3.5 Pest test: Edit Team page renders successfully and contains no Add Team Member / Team Members sections.

## 4. Pivot data cleanup

- [x] 4.1 Migration: `UPDATE team_user SET central_purchasing_role = NULL, is_approver = false WHERE role != 'central_purchasing'` and `UPDATE team_user SET is_approver = false WHERE role = 'central_purchasing' AND (central_purchasing_role IS NULL OR central_purchasing_role != 'finance')` (idempotent; no reverse migration).
- [x] 4.2 Pest test seeding inconsistent pivots and asserting normalization.

## 5. Validation

- [x] 5.1 `vendor/bin/pint --dirty`
- [x] 5.2 `composer test:types` (PHPStan)
- [x] 5.3 `php artisan test --compact tests/Feature/Teams tests/Feature/Erp/CreditLimitApprovalTest.php` plus all new test files
- [x] 5.4 `openspec validate consolidate-team-member-management --strict`
