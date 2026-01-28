# Change: Add Member Management

## Why
Currently, team member management is only available through the team settings page (EditTeam). Users need a dedicated interface to:
- View all team members in a list format
- Easily add new team members with role selection
- View individual member details
- Edit or remove members from a centralized location
- View pending team invitations (for administrators)

This will improve the user experience by providing a dedicated "Team Members" menu item before "Workflow" that consolidates all team member management functionality.

## What Changes
- **ADDED**: New `MemberResource` Filament resource for team member management
  - Uses `Membership` model as the resource model
  - Provides ListMembers and ViewMember pages
  - Table displays: avatar, name, email, role, and joined date
  - Table rows are clickable and navigate to ViewMember page
  - "Add Team Member" button aligned with page title (matching Articles page pattern)
- **ADDED**: `ListMembers` page (`app/Filament/Resources/MemberResource/Pages/ListMembers.php`)
  - Displays team members table
  - Header action: "Add Team Member" button (opens modal with email and role form)
  - Footer widget: `PendingInvitationsWidget` (admin-only)
- **ADDED**: `ViewMember` page (`app/Filament/Resources/MemberResource/Pages/ViewMember.php`)
  - Displays member details: avatar, name, email, role, team name, joined date
  - Header actions: Edit (sliding panel modal) and Remove (confirmation)
  - Edit action form: profile photo, name, email, role
- **ADDED**: `MembershipPolicy` (`app/Policies/MembershipPolicy.php`)
  - Authorization for viewing, creating, updating, and deleting memberships
  - Ensures users can only manage memberships within their current team
- **ADDED**: Custom Blade view for ListMembers page (`resources/views/filament/resources/member-resource/pages/list-members.blade.php`)
  - Displays team members table
  - Conditionally shows PendingTeamInvitations Livewire component (admin-only)
  - Uses `getPendingInvitationsTeam()` method to check admin role
- **MODIFIED**: Navigation in AppPanelProvider - added "Team Members" menu item before "Workflow"
  - Non-collapsible menu item (direct navigation)
  - Uses `heroicon-o-users` icon
- **MODIFIED**: `AddTeamMember` Livewire component to prevent redirect when used on members page

## Implementation Details

### Table Structure
- **Columns**: Avatar (circular, 32px), Name (searchable, sortable), Email (searchable, sortable, copyable), Role (badge with color coding), Joined (dateTime, sortable, toggleable)
- **Filters**: Role filter (admin/editor)
- **Actions**: None (rows are clickable to navigate to ViewMember page)
- **Header Actions**: "Add Team Member" button (opens modal)

### ViewMember Page
- **Info List**: Two sections - "Member Information" and "Team Details"
- **Header Actions**: ActionGroup with Edit and Remove actions
- **Edit Action**: 
  - Sliding panel modal (`slideOver()`)
  - Form fields: profile photo (with image editor), name, email, role
  - Updates user profile and membership role
- **Remove Action**: 
  - Confirmation modal
  - Uses `RemoveTeamMember` action
  - Redirects to list page after removal

### Authorization
- Uses `MembershipPolicy` for resource-level authorization
- Gate checks for actions: `addTeamMember`, `updateTeamMember`, `removeTeamMember`
- Pending invitations widget only visible to administrators

## Impact
- **Affected specs**: `team-management` (new requirements for member resource)
- **Affected code**:
  - `app/Providers/Filament/AppPanelProvider.php` - Navigation menu item addition
  - `app/Filament/Resources/MemberResource.php` - New resource
  - `app/Filament/Resources/MemberResource/Pages/ListMembers.php` - List page with header actions
  - `app/Filament/Resources/MemberResource/Pages/ViewMember.php` - View page with edit/remove actions
  - `resources/views/filament/resources/member-resource/pages/list-members.blade.php` - Custom view with pending invitations
  - `app/Policies/MembershipPolicy.php` - Authorization policy
  - `app/Livewire/App/Teams/AddTeamMember.php` - Modified to prevent redirect on members page
- **Breaking changes**: None
- **Migration required**: No
