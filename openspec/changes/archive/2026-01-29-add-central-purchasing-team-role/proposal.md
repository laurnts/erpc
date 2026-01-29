# Change: Add Central Purchasing Team Role

## Why
Currently, team members can only be assigned two roles: Administrator and Editor. There is a need to add a third role called "Central Purchasing" that has the same permissions as Editor (read, create, and update), but requires an additional sub-role selection. When a team member is assigned the Central Purchasing role, they must also select one of four sub-roles: Key Account, Dept. Head of Sales, Deputy Director, or Director.

This enhancement will allow teams to better categorize and manage Central Purchasing personnel with their specific hierarchical roles, which is important for workflow and approval processes within the ERP system.

## What Changes
- **ADDED**: New `central_purchasing` team role in JetstreamServiceProvider
  - Same permissions as `editor` role (read, create, update)
  - Description: "Central Purchasing users have the ability to read, create, and update."
- **ADDED**: Database migration to add `central_purchasing_role` column to `team_user` and `team_invitations` tables
  - Nullable string column storing CentralPurchasingRole enum values
  - Only populated when role is `central_purchasing`
  - Migration: `2026_01_29_030708_add_central_purchasing_role_to_team_user_table.php`
- **MODIFIED**: Role selection UI in three locations:
  - `MemberResource/Pages/ListMembers.php` - Add Team Member modal
  - `MemberResource/Pages/ViewMember.php` - Edit Member form
  - `Livewire/App/Teams/AddTeamMember.php` - Add Team Member component
  - Radio button options now include "Central Purchasing"
  - Conditional Select field appears when Central Purchasing is selected (using `live()`)
  - Select options: Key Account, Dept. Head of Sales, Deputy Director, Director
  - Form validation requires sub-role when Central Purchasing is selected
- **MODIFIED**: `MemberResource.php` table display
  - Role badge color coding updated to include `central_purchasing` role (success color)
  - Filter options updated to include Central Purchasing
- **MODIFIED**: `ViewMember.php` info list and edit form
  - Role badge displays formatted role name with sub-role (e.g., "Central Purchasing - Key Account")
  - Edit form properly updates role and clears central_purchasing_role when role changes
  - Fixed: Role updates now work correctly (always updates, not just when changed)
- **MODIFIED**: `InviteTeamMember` and `AddTeamMember` actions
  - Handle `central_purchasing_role` parameter when role is `central_purchasing`
  - Validate that central_purchasing_role is provided when role is central_purchasing
  - Store central_purchasing_role in respective tables
- **MODIFIED**: `Membership` and `TeamInvitation` models
  - Added `central_purchasing_role` cast to CentralPurchasingRole enum
  - Updated `getRoleNameAttribute()` to append sub-role label when role is central_purchasing

## Impact
- **Affected specs**: `team-management` (modified requirements for role management)
- **Affected code**:
  - `app/Providers/JetstreamServiceProvider.php` - New role definition
  - `database/migrations/2026_01_29_030708_add_central_purchasing_role_to_team_user_table.php` - New migration
  - `app/Filament/Resources/MemberResource/Pages/ListMembers.php` - Role selection UI
  - `app/Filament/Resources/MemberResource/Pages/ViewMember.php` - Role selection, display, and update logic
  - `app/Filament/Resources/MemberResource.php` - Table display and filters
  - `app/Livewire/App/Teams/AddTeamMember.php` - Role selection UI
  - `app/Actions/Jetstream/InviteTeamMember.php` - Handle central_purchasing_role
  - `app/Actions/Jetstream/AddTeamMember.php` - Handle central_purchasing_role
  - `app/Models/Membership.php` - Model updates with cast and accessor
  - `app/Models/TeamInvitation.php` - Model updates with fillable and cast
- **Breaking changes**: None
- **Migration required**: Yes - database migration to add column

## Implementation Notes

### Completed Implementation
- All core functionality implemented and tested
- Database migration successfully applied
- Role selection UI working in all three locations with conditional sub-role display
- Validation working correctly for required sub-role selection
- Role updates properly refresh the view after saving

### Issues Resolved
- **Role Update Issue**: Fixed ViewMember edit form to always update role (not just when changed), ensuring role changes are properly saved and displayed
- **View Refresh**: Ensured membership record is properly refreshed after pivot table updates to reflect changes in the UI

### Technical Details
- Used `live()` on Radio component to enable reactive conditional Select field
- CentralPurchasingRole enum reused from existing People model implementation
- Sub-role displayed in role name via `getRoleNameAttribute()` method (e.g., "Central Purchasing - Key Account")
- Both `team_user` and `team_invitations` tables updated to support central_purchasing_role
