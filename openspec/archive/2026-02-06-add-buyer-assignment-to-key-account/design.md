# Design: Buyer Assignment for Key Account Team Members

## Context
The `key_account_buyers` pivot table currently references the `key_accounts` table, which was removed when key accounts were migrated to team members. The table structure needs to be updated to reference `users` table instead. Additionally, buyer assignment UI needs to be added to the ViewMember page for key account team members.

## Goals
- Update `key_account_buyers` table to reference `users` table instead of non-existent `key_accounts` table
- Add buyer assignment UI to ViewMember page for key account team members
- Enable buyer filtering in KeyAccountSelect component
- Filter "Prepared By" options in QE/PNL forms based on buyer assignment

## Non-Goals
- Changing the buyer assignment data model (many-to-many relationship remains)
- Adding buyer assignment to other Central Purchasing roles (only Key Account)
- Changing how buyer assignment affects other workflows beyond QE/PNL

## Decisions

### Decision: Update key_account_buyers Table Structure
**Rationale**: The table currently references `key_accounts` table which doesn't exist. It needs to reference `users` table to work with the team member system.

**Implementation**:
- Drop foreign key constraint on `key_account_id` referencing `key_accounts`
- Update column to reference `users.id` instead
- Keep cascade delete behavior
- Migrate existing data if any (though likely none exists since key_accounts was removed)

**Alternatives considered**:
- Create new table `key_account_user_buyers` - Rejected: unnecessary complexity, can update existing table
- Remove table entirely - Rejected: needed for buyer assignment feature

### Decision: Use Relation Manager Pattern for Buyer Assignment UI
**Rationale**: Consistent with existing article-supplier relationship pattern. Filament RelationManager provides built-in attach/detach functionality.

**Implementation**:
- Create `BuyersRelationManager` similar to `SuppliersRelationManager`
- Register in ViewMember page's `getRelations()` method
- Conditionally show only when `central_purchasing_role === KEY_ACCOUNT`

**Alternatives considered**:
- Custom form section - Rejected: RelationManager provides better UX and consistency
- Separate page - Rejected: buyer assignment is part of member management, should be on same page

### Decision: Filter Key Accounts by Buyer Assignment
**Rationale**: Business requirement - key accounts should only handle assigned buyers. When creating QE/PNL, only assigned key accounts should appear in "Prepared By" dropdown.

**Implementation**:
- Update `KeyAccountSelect::makeWithRelationship()` to filter by buyer when `$buyerId` is provided
- Query via `key_account_buyers` pivot table
- Fallback to showing all key accounts if no buyer ID provided or no assignments exist

**Alternatives considered**:
- Always show all key accounts - Rejected: doesn't meet business requirement
- Hide field if no assignments - Rejected: too restrictive, fallback is better UX

### Decision: Get Buyer ID from Request/BuyerQuote Relationship
**Rationale**: QE and PNL documents are related to requests/buyer quotes, which have buyers. Need to extract buyer ID from these relationships.

**Implementation**:
- For QE: Get buyer from `request.buyer_id`
- For PNL: Get buyer from `buyer_quote.request.buyer_id`
- Pass buyer ID to `KeyAccountSelect` component

**Alternatives considered**:
- Store buyer ID directly on QE/PNL - Rejected: redundant, can get from relationships
- User selects buyer first - Rejected: buyer is already determined by request/quote

## Risks / Trade-offs

### Risk: Migration Complexity
**Mitigation**: Check if any data exists in `key_account_buyers` table. If empty, migration is straightforward. If data exists, need to map `key_accounts.id` to `users.id` (likely via email matching).

### Risk: Breaking Existing Functionality
**Mitigation**: The `key_account_buyers` table currently has broken foreign keys (references non-existent table). Updating it will fix, not break, functionality.

### Trade-off: Fallback Behavior
**Decision**: Show all key accounts if no buyer assignment exists. This allows system to work even if assignments aren't configured yet.

## Migration Plan

### Step 1: Database Migration
1. Create migration to update `key_account_buyers` table
2. Check for existing data
3. If data exists, map `key_accounts.id` to `users.id` (via email or name matching)
4. Drop old foreign key constraint
5. Add new foreign key constraint to `users.id`
6. Test migration rollback

### Step 2: Model Updates
1. Update `Membership` model to add `buyers()` relationship
2. Verify `Company::keyAccounts()` relationship works correctly
3. Test relationships in tinker

### Step 3: UI Implementation
1. Create `BuyersRelationManager`
2. Add to ViewMember page
3. Test attach/detach operations

### Step 4: Component Updates
1. Update `KeyAccountSelect` to filter by buyer
2. Update QE form to pass buyer ID
3. Update PNL form to pass buyer ID
4. Test filtering behavior

## Open Questions
- Should buyer assignments be team-scoped? (Likely yes, but verify)
- What happens if a key account is removed from team but has buyer assignments? (Cascade delete should handle)
