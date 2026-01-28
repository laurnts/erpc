## 1. Navigation Setup
- [x] 1.1 Add "Team Members" navigation menu item before "Workflow" in AppPanelProvider
- [x] 1.2 Set appropriate icon (`heroicon-o-users`) for Team Members menu item
- [x] 1.3 Set navigation sort order to 1

## 2. Member Resource
- [x] 2.1 Create MemberResource class (`app/Filament/Resources/MemberResource.php`)
- [x] 2.2 Configure resource to use Membership model
- [x] 2.3 Set navigation group to null (direct menu item, non-collapsible)
- [x] 2.4 Set navigation icon (`heroicon-o-users`) and sort order (1)
- [x] 2.5 Configure table with columns: avatar, name, email, role, joined date
- [x] 2.6 Add role filter (SelectFilter)
- [x] 2.7 Make table rows clickable (recordUrl) to navigate to ViewMember page
- [x] 2.8 Remove table row actions (no View action or ActionGroup per row)

## 3. List Members Page
- [x] 3.1 Create ListMembers page (`app/Filament/Resources/MemberResource/Pages/ListMembers.php`)
- [x] 3.2 Add "Add Team Member" header action (aligned with page title, matching Articles page pattern)
  - Opens modal with email and role form
  - Uses InviteTeamMember action
- [x] 3.3 Add PendingInvitationsWidget as footer widget (admin-only)
- [x] 3.4 Configure custom view path for footer widget integration

## 4. View Member Page
- [x] 4.1 Create ViewMember page (`app/Filament/Resources/MemberResource/Pages/ViewMember.php`)
- [x] 4.2 Create info list with two sections:
  - Member Information: avatar, name, email, role
  - Team Details: team name, joined date, last updated
- [x] 4.3 Add header ActionGroup with Edit and Remove actions
- [x] 4.4 Implement Edit action:
  - Sliding panel modal (`slideOver()`)
  - Form fields: profile photo (with image editor), name, email, role
  - Updates user profile and membership role
- [x] 4.5 Implement Remove action:
  - Confirmation modal
  - Uses RemoveTeamMember action
  - Redirects to list page after removal

## 5. Authorization
- [x] 5.1 Create MembershipPolicy (`app/Policies/MembershipPolicy.php`)
  - viewAny: verified users with current team
  - view: user belongs to membership team
  - create: user owns team
  - update: user owns team
  - delete: user owns team and not removing self
- [x] 5.2 Add Gate checks for actions:
  - addTeamMember: Gate::check('addTeamMember', $team)
  - updateRole: Gate::check('updateTeamMember', $team)
  - remove: Gate::check('removeTeamMember', $team)
- [x] 5.3 Ensure PendingInvitationsWidget visibility is admin-only (hasTeamRole check)

## 6. Custom View for Pending Invitations
- [x] 6.1 Create custom Blade view for ListMembers page (`resources/views/filament/resources/member-resource/pages/list-members.blade.php`)
- [x] 6.2 Embed PendingTeamInvitations Livewire component conditionally (admin-only)
- [x] 6.3 Add `getPendingInvitationsTeam()` method to ListMembers page for admin check

## 7. Component Modifications
- [x] 7.1 Modify AddTeamMember Livewire component to prevent redirect when on members page
- [x] 7.2 Reset form after successful team member addition

## 8. Testing
- [x] 8.1 Test member listing (table displays correctly)
- [x] 8.2 Test member viewing (clickable rows navigate to ViewMember page)
- [x] 8.3 Test adding team member (invite via modal)
- [x] 8.4 Test editing member (sliding panel modal with form)
- [x] 8.5 Test editing member role (role update works)
- [x] 8.6 Test removing member (confirmation and removal)
- [x] 8.7 Test pending invitations visibility (admin vs non-admin)
- [x] 8.8 Test authorization policies (MembershipPolicy works correctly)
