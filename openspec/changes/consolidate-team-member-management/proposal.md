# Change: Consolidate team member management into the Team Members page

## Why

Team member management exists twice: the sidebar "Team Members" page (`MemberResource`, team-built) and the "Edit Team" tenant profile page (Relaticle/Jetstream original). The two have drifted: Edit Team's role modal writes only `role`, leaving stale `central_purchasing_role`/`is_approver` pivot data (violating the current team-management spec), and invitation acceptance loses the Central Purchasing sub-role entirely because Jetstream's vendor controller never forwards it.

## What Changes

- Edit Team becomes pure team settings (name, company info, branding, delete team); its Add Team Member, Pending Team Invitations, and Team Members sections are removed
- Delete the now-dead `App\Livewire\App\Teams\AddTeamMember` and `App\Livewire\App\Teams\TeamMembers` components, their blade views, dead legacy Jetstream blades (`teams/show`, `teams/create`), and orphaned `teams.*` translation keys (`PendingTeamInvitations` survives — the Team Members page embeds it; `teams/team-member-manager.blade.php` survives — vendor Jetstream tests render it)
- New shared `UpdateTeamMemberRole` action owns all role writes: clears `central_purchasing_role` and `is_approver` when the role no longer qualifies
- Fix invitation acceptance to copy `central_purchasing_role` from the invitation to the membership
- Team Members page gains: "Leave team" action on the authenticated user's own row, and an Owner card (the owner has no Membership pivot row and was invisible in every list)
- Member edit no longer changes login email or password (global account credentials); name and photo remain editable
- One-off cleanup migration normalizes stale pivot rows (`is_approver`/`central_purchasing_role` set on non-qualifying roles)

## Impact

- Affected specs: `team-management`
- Affected code:
  - `app/Filament/Pages/EditTeam.php` (remove member sections)
  - `app/Livewire/App/Teams/{AddTeamMember,TeamMembers}.php` + views (delete)
  - `resources/views/teams/{show,create}.blade.php` (delete, confirmed dead)
  - `app/Actions/Teams/UpdateTeamMemberRole.php` (new)
  - `app/Actions/Jetstream/AddTeamMember.php` (copy sub-role from invitation)
  - `app/Filament/Resources/MemberResource.php` + `Pages/{ListMembers,ViewMember}.php` + `list-members.blade.php` (leave action, owner card, restricted edit)
  - `lang/en/teams.php` (prune orphaned keys)
  - New migration: pivot data cleanup
- Test coverage note: existing `tests/Feature/Teams/*` tests exercise the vendor `TeamMemberManager` component, not the code being changed; new tests must cover the `MemberResource` path, which currently has none
