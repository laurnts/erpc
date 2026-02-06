# Change: Add Buyer Assignment to Key Account Team Members

**Status**: ✅ Completed  
**Completed Date**: 2026-02-06

## Why
Key Account team members need to be assigned to specific buyers to restrict which key accounts can handle which buyers. This ensures that when creating Quotation Evaluation (QE) or Profit and Loss (PNL) documents, the "Prepared By" field only shows key accounts assigned to handle the request's buyer. This change will:

- Enable buyer assignment management for key account team members
- Filter key account selection in QE/PNL forms based on buyer assignment
- Provide a consistent UI pattern similar to article-supplier relationships
- Complete the buyer assignment feature that was partially implemented (TODO comments exist)

## What Changes
- **ADDED**: Buyer Assignment relation manager to ViewMember page
  - New `BuyersRelationManager` for key account team members
  - Placed under "Member Information" and "Team Details" sections
  - Only visible when team member has Key Account role
  - Similar UI pattern to Article-Supplier relationship
- **MODIFIED**: Database migration to update `key_account_buyers` table
  - Update foreign key from `key_accounts` table to `users` table
  - Migrate existing data if any
  - Ensure proper cascade delete behavior
- **MODIFIED**: `KeyAccountSelect` component
  - Enable buyer filtering when `$buyerId` is provided
  - Filter key accounts to only show those assigned to the buyer
- **MODIFIED**: Quotation Evaluation and PNL forms
  - Pass buyer ID to `KeyAccountSelect` component
  - Filter "Prepared By" options based on buyer assignment
- **MODIFIED**: Company model `keyAccounts()` relationship
  - Ensure relationship works correctly with updated table structure

## Impact
- **Affected specs**: `team-management` (member management), `erp-quoting` (QE/PNL workflow)
- **Affected code**:
  - `app/Filament/Resources/MemberResource/Pages/ViewMember.php` - Add BuyersRelationManager
  - `app/Filament/Resources/MemberResource/RelationManagers/BuyersRelationManager.php` - New relation manager
  - `app/Filament/Forms/Components/KeyAccountSelect.php` - Enable buyer filtering
  - `app/Models/Company.php` - Verify keyAccounts() relationship
  - `app/Models/Membership.php` - Add buyers() relationship
  - `database/migrations/YYYY_MM_DD_HHMMSS_update_key_account_buyers_to_users.php` - Update foreign keys
- **Breaking changes**: None (table structure update only affects unused/broken references)
- **Migration required**: Yes - Update `key_account_buyers` table foreign keys
