## 1. Database Changes
- [x] 1.1 Create migration to add `central_purchasing_role` column to `team_user` table
  - Column type: nullable enum (CentralPurchasingRole)
  - Also added to `team_invitations` table for invitation support
  - Migration: `2026_01_29_030708_add_central_purchasing_role_to_team_user_table.php`

## 2. Role Definition
- [x] 2.1 Add `central_purchasing` role in `JetstreamServiceProvider.php`
  - Permissions: read, create, update (same as editor)
  - Description: "Central Purchasing users have the ability to read, create, and update."

## 3. Model Updates
- [x] 3.1 Update `Membership` model
  - Added `central_purchasing_role` cast to CentralPurchasingRole enum
  - Updated `getRoleNameAttribute()` to append sub-role label when role is central_purchasing
- [x] 3.2 Update `TeamInvitation` model
  - Added `central_purchasing_role` to fillable
  - Added cast to CentralPurchasingRole enum

## 4. UI Components - ListMembers Page
- [x] 4.1 Update role Radio component in `ListMembers.php`
  - Added 'central_purchasing' => 'Central Purchasing' option
  - Added description for central_purchasing role
- [x] 4.2 Add conditional Select field for central_purchasing_role
  - Shows when role is 'central_purchasing' (using `live()`)
  - Options from CentralPurchasingRole enum
  - Required when role is central_purchasing
- [x] 4.3 Update form action to pass central_purchasing_role to InviteTeamMember

## 5. UI Components - ViewMember Page
- [x] 5.1 Update role Radio component in `ViewMember.php`
  - Added 'central_purchasing' => 'Central Purchasing' option
  - Added description for central_purchasing role
- [x] 5.2 Add conditional Select field for central_purchasing_role
  - Shows when role is 'central_purchasing' (using `live()`)
  - Pre-populates with existing value from membership
  - Required when role is central_purchasing
- [x] 5.3 Update form fillForm to include central_purchasing_role
- [x] 5.4 Update form action to save central_purchasing_role to pivot table
  - Fixed: Always updates role (not just when changed)
  - Properly handles clearing central_purchasing_role when role changes
  - Refreshes membership record after update
- [x] 5.5 Update info list to display central_purchasing_role when applicable
  - Sub-role is displayed in role name via `getRoleNameAttribute()`
  - Badge color updated to 'success' for central_purchasing role

## 6. UI Components - AddTeamMember Livewire
- [x] 6.1 Update role Radio component in `AddTeamMember.php`
  - Dynamically includes central_purchasing from Jetstream roles
  - Added conditional Select field for central_purchasing_role
  - Shows when role is 'central_purchasing' (using `live()`)
  - Required when role is central_purchasing
  - Updated form reset to include central_purchasing_role

## 7. MemberResource Table Display
- [x] 7.1 Update role badge color in `MemberResource.php`
  - Added 'central_purchasing' => 'success' color
- [x] 7.2 Update role filter options
  - Added 'central_purchasing' => 'Central Purchasing'
- [ ] 7.3 Consider displaying sub-role in table if needed (optional enhancement)
  - Deferred: Sub-role is shown in detail view via role name formatting

## 8. Action Classes
- [x] 8.1 Update `InviteTeamMember.php`
  - Accepts central_purchasing_role parameter
  - Stores in team_invitations table
  - Validates central_purchasing_role is provided when role is central_purchasing
- [x] 8.2 Update `AddTeamMember.php`
  - Accepts central_purchasing_role parameter
  - Stores in team_user pivot table
  - Validates central_purchasing_role is provided when role is central_purchasing

## 9. Validation
- [x] 9.1 Add validation rules for central_purchasing_role
  - Required when role is 'central_purchasing'
  - Must be valid CentralPurchasingRole enum value
  - Nullable when role is not 'central_purchasing'
  - Implemented in both InviteTeamMember and AddTeamMember actions

## 10. Testing
- [x] 10.1 Test adding team member with Central Purchasing role
- [x] 10.2 Test editing team member role to/from Central Purchasing
- [x] 10.3 Test validation when central_purchasing_role is missing
- [x] 10.4 Test display of Central Purchasing role in table and view pages
- [x] 10.5 Test filtering by Central Purchasing role
