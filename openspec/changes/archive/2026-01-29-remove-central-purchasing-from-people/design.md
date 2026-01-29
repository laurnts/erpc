# Design: Remove Central Purchasing from People Model

## Context
Central Purchasing role management has been successfully moved to the team member level. The People model currently still contains `is_central_purchasing` and `central_purchasing_role` fields that are no longer needed. All Central Purchasing functionality should now reference team members (Membership model) instead of People records.

## Goals
- Remove Central Purchasing fields from People model
- Update all queries and relationships to use team members instead
- Migrate existing data from People Central Purchasing to team members
- Maintain data integrity during migration
- Ensure all approval workflows continue to work with team members

## Non-Goals
- Changing the Central Purchasing role enum itself
- Modifying team member role functionality
- Changing approval workflow logic (only the data source)

## Decisions

### Decision: Query Team Members via Membership Model
**Rationale**: Team members are stored in the `team_user` pivot table with role information. We need to query Users who have Central Purchasing role in the current team context.

**Implementation**: Create helper methods that:
1. Query Membership model filtered by role = 'central_purchasing' and central_purchasing_role = specific role
2. Return User models with the Membership relationship loaded
3. Use Filament::getTenant() to get current team context

**Alternatives considered**:
- Query User model directly: Would require joining team_user table, less clean
- Create separate service class: Over-engineered for simple queries

### Decision: Update Foreign Keys to Reference Users
**Rationale**: Since Central Purchasing personnel are now team members (Users), foreign keys should reference `users.id` instead of `people.id`. This aligns with the new data model.

**Implementation**: 
- Update foreign key columns in quotation_evaluations, profit_and_loss, buyer_quotes tables
- Change column type if needed (ensure compatible with users.id)
- Update model relationships from belongsTo(People) to belongsTo(User)

**Alternatives considered**:
- Keep People IDs and map them: Would require ongoing mapping logic, adds complexity
- Create junction table: Over-engineered, direct foreign key is simpler

### Decision: Data Migration Strategy
**Rationale**: Existing Central Purchasing People records need to be converted to team members. We need to:
1. Match People records to Users (by email or name)
2. Create team memberships with Central Purchasing role
3. Update foreign key references

**Implementation**:
- Create data migration script that runs after schema migration
- For each Central Purchasing People record:
  - Try to find User by email (if People has email custom field) or name
  - If User exists, add to team with Central Purchasing role
  - If User doesn't exist, create User account (may require manual intervention)
  - Update all foreign key references from People ID to User ID
- Handle edge cases (multiple matches, no matches, etc.)

**Alternatives considered**:
- Manual migration: Too error-prone for production data
- Keep both systems temporarily: Would create confusion and duplicate data

### Decision: Handle key_account_buyers Table
**Rationale**: This table links People (key accounts) to Companies (buyers). Since key accounts are now team members, we need to decide if this table should:
1. Reference users instead of people
2. Be removed entirely (if buyer assignment is handled differently)

**Implementation**: 
- Investigate current usage of key_account_buyers table
- If still needed, update to reference users table
- If not needed, remove table and relationship

**Alternatives considered**:
- Keep as-is: Would break with new model
- Create new table: Only if structure needs to change significantly

## Database Schema Changes

### Migration 1: Update Foreign Keys
```php
Schema::table('quotation_evaluations', function (Blueprint $table) {
    $table->foreignId('prepared_by_id')->nullable()->change();
    $table->foreignId('dept_head_sales_id')->nullable()->change();
    $table->foreignId('deputy_director_id')->nullable()->change();
    $table->foreignId('approved_by_id')->nullable()->change();
    // Drop old foreign key constraints
    // Add new foreign key constraints to users table
});

// Repeat for profit_and_loss and buyer_quotes tables
```

### Migration 2: Remove Columns from People
```php
Schema::table('people', function (Blueprint $table) {
    $table->dropColumn('is_central_purchasing');
    $table->dropColumn('central_purchasing_role');
    // Drop check constraints if any
});
```

### Migration 3: Data Migration
- Map People IDs to User IDs
- Update foreign key values
- Create team memberships

## Risks / Trade-offs

### Risk: Data Loss During Migration
**Mitigation**: 
- Create comprehensive backup before migration
- Test migration script on staging data first
- Implement rollback strategy
- Log all migration actions for audit

### Risk: User Matching Failures
**Mitigation**:
- Use multiple matching strategies (email, name)
- Provide manual review process for unmatched records
- Create placeholder Users if needed (with clear naming convention)

### Risk: Breaking Existing Workflows
**Mitigation**:
- Thoroughly test all approval workflows after migration
- Update all queries before removing fields
- Provide fallback queries during transition period if needed

### Trade-off: Migration Complexity vs Clean Slate
**Decision**: Perform migration to preserve existing data and relationships. Starting fresh would lose historical approval data.

## Migration Plan

1. **Phase 1: Preparation**
   - Create helper methods for querying team members
   - Update all queries to use team members (but keep People fields temporarily)
   - Test queries work correctly

2. **Phase 2: Schema Migration**
   - Update foreign key columns to reference users
   - Run data migration script
   - Verify data integrity

3. **Phase 3: Code Cleanup**
   - Remove Central Purchasing fields from People model
   - Remove form fields from PeopleResource
   - Remove unused queries and methods

4. **Phase 4: Validation**
   - Test all approval workflows
   - Verify no broken references
   - Update documentation

## Implementation Notes

### View Page URL Updates
During implementation, it was discovered that the Central Purchasing personnel names in ViewProfitAndLoss and ViewQuotationEvaluation pages were still linking to PeopleResource URLs. These were updated to use MemberResource URLs instead:

- **ViewProfitAndLoss.php**: All 4 personnel fields (preparedBy, deptHeadSales, deputyDirector, approvedBy) now link to team member pages
- **ViewQuotationEvaluation.php**: All 4 personnel fields now link to team member pages
- **Implementation**: Uses Membership lookup by team_id and user_id to find the correct Membership record, then generates MemberResource URL

This ensures that clicking on Central Purchasing personnel names navigates to the team member detail page rather than a People page.

## Open Questions
- How to handle People records that don't have matching Users?
  - **Decision**: Create User accounts automatically with email from custom fields, or mark for manual review
- Should key_account_buyers table be updated or removed?
  - **Decision**: Investigate usage first, then decide. Currently still references people table. Buyer filtering in KeyAccountSelect temporarily disabled.
- What to do with historical People records that were Central Purchasing?
  - **Decision**: Keep People records but remove Central Purchasing flags. Historical relationships preserved via foreign keys (now pointing to Users).
