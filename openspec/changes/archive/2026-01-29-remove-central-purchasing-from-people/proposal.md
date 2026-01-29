# Change: Remove Central Purchasing from People Model

## Why
Central Purchasing role management has been moved to the team member level (Membership model). The People model should no longer store Central Purchasing role information since this is now handled through team member roles. This change will:

- Eliminate duplicate role management (People vs Team Members)
- Simplify the data model by having a single source of truth for Central Purchasing roles
- Align with the new team member role system where Central Purchasing is a team-level role
- Reduce confusion about where Central Purchasing personnel are managed

## What Changes
- **REMOVED**: `is_central_purchasing` and `central_purchasing_role` fields from People model
  - ✅ Removed from fillable array
  - ✅ Removed from casts
  - ✅ Removed from database schema (migration created)
- **REMOVED**: Central Purchasing toggle and role select from PeopleResource form
  - ✅ Removed from `getFormSchema()` method
- **MODIFIED**: All queries that filter People by Central Purchasing role
  - ✅ Updated to query team members (Membership) with Central Purchasing role instead
  - ✅ Affected files updated:
    - `KeyAccountSelect.php` - Now queries team members instead of People
    - `BuyerQuotesRelationManager.php` - All 4 personnel selects now use TeamMemberService
    - `QuotationEvaluationResource.php` - getKeyAccountOptions() and createKeyAccount() updated
    - `ProfitAndLossResource.php` - getKeyAccountOptions() and createKeyAccount() updated
    - `Company.php` - keyAccounts() relationship updated to query team members
    - `QuotationEvaluationForm.php` - getKeyAccountOptions() and createKeyAccount() updated
- **MODIFIED**: ViewPeople page
  - ✅ Removed BuyersRelationManager condition (Key Accounts now managed as team members)
- **MODIFIED**: Database relationships
  - ✅ Updated foreign key references from People IDs to User IDs
  - ✅ QuotationEvaluation model relationships updated (belongsTo User instead of People)
  - ✅ ProfitAndLoss model relationships updated (belongsTo User instead of People)
  - ✅ Data migration script created to map existing records
- **MODIFIED**: View page URLs
  - ✅ ViewProfitAndLoss Central Purchasing section URLs updated to MemberResource
  - ✅ ViewQuotationEvaluation Central Purchasing section URLs updated to MemberResource
  - ✅ All personnel names now link to team member pages instead of People pages

## Impact
- **Affected specs**: `crm-core` (People model changes), `erp-quoting` (QE/PNL approval workflow changes)
- **Affected code** (all completed):
  - ✅ `app/Models/People.php` - Removed Central Purchasing fields
  - ✅ `app/Filament/Resources/PeopleResource.php` - Removed form fields
  - ✅ `app/Filament/Resources/PeopleResource/Pages/ViewPeople.php` - Removed BuyersRelationManager condition
  - ✅ `app/Filament/Forms/Components/KeyAccountSelect.php` - Now queries team members
  - ✅ `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php` - Updated personnel selects
  - ✅ `app/Filament/Resources/QuotationEvaluationResource.php` - Updated key account queries
  - ✅ `app/Filament/Resources/ProfitAndLossResource.php` - Updated key account queries
  - ✅ `app/Filament/Resources/QuotationEvaluationResource/Pages/ViewQuotationEvaluation.php` - Updated URLs
  - ✅ `app/Filament/Resources/ProfitAndLossResource/Pages/ViewProfitAndLoss.php` - Updated URLs
  - ✅ `app/Models/Company.php` - Updated keyAccounts relationship
  - ✅ `app/Models/QuotationEvaluation.php` - Updated relationships to User
  - ✅ `app/Models/ProfitAndLoss.php` - Updated relationships to User
  - ✅ `app/Livewire/QuotationEvaluationForm.php` - Updated key account queries
  - ✅ `app/Services/TeamMemberService.php` - New helper service created
  - ✅ `database/migrations/2026_01_29_043502_update_central_purchasing_foreign_keys_to_users.php` - Foreign key migration
  - ✅ `database/migrations/2026_01_29_043515_remove_central_purchasing_from_people_table.php` - Column removal migration
  - ✅ `database/migrations/2026_01_29_043736_migrate_central_purchasing_people_to_team_members.php` - Data migration
- **Breaking changes**: **BREAKING** - Existing Central Purchasing People records will need migration to team members
- **Migration required**: Yes - All migrations created and ready for execution
- **Status**: ✅ **IMPLEMENTATION COMPLETE** - Ready for testing and migration execution

## Migration Strategy

### Data Migration Considerations
1. **Existing Central Purchasing People records**: Need to be mapped to team members
   - For each People record with `is_central_purchasing = true`:
     - Find or create corresponding User (by email or name matching)
     - Add User to team as Central Purchasing member with appropriate sub-role
     - Update foreign key references in QE/PNL documents from People ID to User ID
2. **Foreign Key Updates**: 
   - `quotation_evaluations.prepared_by_id` - Change from People ID to User ID
   - `quotation_evaluations.dept_head_sales_id` - Change from People ID to User ID
   - `quotation_evaluations.deputy_director_id` - Change from People ID to User ID
   - `quotation_evaluations.approved_by_id` - Change from People ID to User ID
   - `profit_and_loss.prepared_by_id` - Change from People ID to User ID
   - `profit_and_loss.dept_head_sales_id` - Change from People ID to User ID
   - `profit_and_loss.deputy_director_id` - Change from People ID to User ID
   - `profit_and_loss.approved_by_id` - Change from People ID to User ID
   - `buyer_quotes.prepared_by_id` - Change from People ID to User ID
   - `buyer_quotes.dept_head_sales_id` - Change from People ID to User ID
   - `buyer_quotes.deputy_director_id` - Change from People ID to User ID
   - `buyer_quotes.approved_by_id` - Change from People ID to User ID
   - `key_account_buyers.key_account_id` - May need to change to user_id or remove if not needed

### Implementation Approach
1. Create helper method to get team members by Central Purchasing role
2. Update all queries to use team members instead of People
3. Update foreign key columns to reference users table instead of people table
4. Create data migration script to map existing data
5. Remove Central Purchasing fields from People model and forms
