## 1. Database Migration
- [x] 1.1 Create migration to update `key_account_buyers` table
  - Drop foreign key constraint on `key_account_id` referencing `key_accounts`
  - Update column to reference `users.id` instead
  - Add proper cascade delete behavior
  - Migrate existing data if any (map key_accounts.id to users.id)

## 2. Model Relationships
- [x] 2.1 Update Membership model
  - Add `buyers()` relationship method (belongsToMany Company via key_account_buyers)
  - Ensure proper team scoping
- [x] 2.2 Verify Company model `keyAccounts()` relationship
  - Ensure it correctly queries users with Key Account role
  - Verify team scoping works correctly

## 3. Buyer Assignment Relation Manager
- [x] 3.1 Create `BuyersRelationManager` class
  - Similar structure to `SuppliersRelationManager`
  - Filter buyers by `is_buyer = true`
  - Display buyer code, name, and active status
  - Support attach/detach operations
  - Only show for team members with Key Account role
- [x] 3.2 Add relation manager to ViewMember page
  - Register `BuyersRelationManager` in `getRelations()` method
  - Conditionally show only when `central_purchasing_role === KEY_ACCOUNT`

## 4. KeyAccountSelect Component Updates
- [x] 4.1 Enable buyer filtering in `KeyAccountSelect`
  - Remove TODO comment
  - Implement buyer filtering when `$buyerId` is provided
  - Query key accounts assigned to the buyer via `key_account_buyers` table
  - Ensure proper team scoping

## 5. Quotation Evaluation Form Updates
- [x] 5.1 Update QuotationEvaluationResource form
  - Pass buyer ID to `KeyAccountSelect` for "Prepared By" field
  - Get buyer ID from request relationship
  - Already implemented via ApprovalPersonnelSchema
- [x] 5.2 Update QuotationEvaluationForm Livewire component
  - Pass buyer ID to `getKeyAccountOptions()` method
  - Filter key accounts by buyer assignment

## 6. Profit and Loss Form Updates
- [x] 6.1 Update ProfitAndLossResource form
  - Pass buyer ID to `KeyAccountSelect` for "Prepared By" field
  - Get buyer ID from buyer quote relationship
  - Already implemented via ApprovalPersonnelSchema

## 7. Testing
- [ ] 7.1 Test buyer assignment UI
  - Verify relation manager appears only for Key Account role
  - Test attach/detach operations
  - Verify buyer list is filtered correctly
- [ ] 7.2 Test key account filtering in QE form
  - Create QE with buyer assigned to key account
  - Verify only assigned key account appears in "Prepared By" dropdown
- [ ] 7.3 Test key account filtering in PNL form
  - Create PNL with buyer assigned to key account
  - Verify only assigned key account appears in "Prepared By" dropdown
- [ ] 7.4 Test edge cases
  - Key account with no buyers assigned
  - Buyer with no key accounts assigned
  - Multiple key accounts assigned to same buyer
