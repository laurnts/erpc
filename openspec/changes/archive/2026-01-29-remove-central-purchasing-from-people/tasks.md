## 1. Research and Analysis
- [x] 1.1 Identify all foreign key columns that reference People for Central Purchasing roles
- [x] 1.2 Identify all queries that filter People by is_central_purchasing or central_purchasing_role
- [x] 1.3 Document data migration requirements for existing Central Purchasing People records
- [x] 1.4 Check if key_account_buyers table needs updates (TODO: Still references people, may need future update)

## 2. Create Helper Methods
- [x] 2.1 Create helper method to get team members by Central Purchasing role
  - Created `TeamMemberService::getTeamMembersByCentralPurchasingRole()`
  - Created `TeamMemberService::getCentralPurchasingTeamMembers()`
  - Created `TeamMemberService::getTeamMemberOptionsByRole()` for select options
- [x] 2.2 Create helper method to get User ID from People ID for migration
  - Created `TeamMemberService::getUserIdFromPeopleId()` for data migration

## 3. Database Migration
- [x] 3.1 Create migration to update foreign key columns
  - Created `2026_01_29_043502_update_central_purchasing_foreign_keys_to_users.php`
  - Updates foreign keys in quotation_evaluations and profit_and_losses tables
  - Changes from people.id references to users.id references
- [x] 3.2 Create migration to remove Central Purchasing columns from people table
  - Created `2026_01_29_043515_remove_central_purchasing_from_people_table.php`
  - Removes is_central_purchasing and central_purchasing_role columns
- [x] 3.3 Create data migration script
  - Created `2026_01_29_043736_migrate_central_purchasing_people_to_team_members.php`
  - Maps existing Central Purchasing People to team members
  - Updates foreign key references in related tables
  - Handles edge cases (People without matching Users)

## 4. Update Models
- [x] 4.1 Update People model
  - Removed is_central_purchasing from fillable
  - Removed central_purchasing_role from fillable
  - Removed casts for Central Purchasing fields
  - Removed CentralPurchasingRole import
  - Removed Central Purchasing relationships (preparedEvaluations, approvedEvaluations)
- [x] 4.2 Update Company model
  - Updated keyAccounts() relationship to query team members (Users) instead of People
  - Uses whereHas to filter by Central Purchasing role

## 5. Update Forms and Resources
- [x] 5.1 Update PeopleResource
  - Removed Central Purchasing toggle from getFormSchema()
  - Removed Central Purchasing role select from getFormSchema()
- [x] 5.2 Update ViewPeople page
  - Removed BuyersRelationManager condition (no longer shown for Central Purchasing)
  - Added note about Key Accounts being managed as team members
- [x] 5.3 Update KeyAccountSelect component
  - Changed query to use team members instead of People
  - Updated createOptionUsing to create team member (User) instead
  - Updated modifyQueryUsing to query Membership via User teams relationship

## 6. Update Resource Queries
- [x] 6.1 Update BuyerQuotesRelationManager
  - Updated prepared_by_id select to use TeamMemberService
  - Updated dept_head_sales_id select to use TeamMemberService
  - Updated deputy_director_id select to use TeamMemberService
  - Updated approved_by_id select to use TeamMemberService
- [x] 6.2 Update QuotationEvaluationResource
  - Updated getKeyAccountOptions() to query team members via TeamMemberService
  - Updated createKeyAccount() to create team member instead of People
- [x] 6.3 Update ProfitAndLossResource
  - Updated getKeyAccountOptions() to query team members via TeamMemberService
  - Updated createKeyAccount() to create team member instead of People
- [x] 6.4 Update QuotationEvaluationForm Livewire
  - Updated getKeyAccountOptions() to query team members via TeamMemberService
  - Updated createKeyAccount() to create team member instead of People
  - Updated authorization check to use addTeamMember permission

## 7. Update Relationships and Foreign Keys
- [x] 7.1 Update QuotationEvaluation model
  - Changed prepared_by_id, dept_head_sales_id, deputy_director_id, approved_by_id relationships
  - Updated from belongsTo(People) to belongsTo(User)
- [x] 7.2 Update ProfitAndLoss model
  - Changed prepared_by_id, dept_head_sales_id, deputy_director_id, approved_by_id relationships
  - Updated from belongsTo(People) to belongsTo(User)
- [x] 7.3 Update BuyerQuote model
  - Verified BuyerQuote does not have Central Purchasing foreign keys (not needed)
- [x] 7.4 Check key_account_buyers table
  - Table still references people table (noted for future update if needed)
  - Buyer filtering in KeyAccountSelect temporarily disabled

## 8. Update View Pages URLs
- [x] 8.1 Update ViewProfitAndLoss Central Purchasing section URLs
  - Changed from PeopleResource::getUrl() to MemberResource::getUrl()
  - Updated all 4 personnel fields (preparedBy, deptHeadSales, deputyDirector, approvedBy)
  - URLs now correctly point to team member pages
- [x] 8.2 Update ViewQuotationEvaluation Central Purchasing section URLs
  - Changed from PeopleResource::getUrl() to MemberResource::getUrl()
  - Updated all 4 personnel fields
  - URLs now correctly point to team member pages

## 9. Testing
- [ ] 9.1 Test creating new QE/PNL documents with Central Purchasing personnel
- [ ] 9.2 Test editing existing QE/PNL documents
- [ ] 9.3 Test data migration script on staging data
- [ ] 9.4 Verify all foreign key relationships work correctly
- [ ] 9.5 Test People create/edit forms (should not show Central Purchasing fields)
- [ ] 9.6 Test team member role assignment for Central Purchasing
- [ ] 9.7 Test Central Purchasing personnel URLs in QE/PNL views

## 10. Cleanup
- [x] 10.1 Remove unused imports (CentralPurchasingRole from People model, People imports from resources)
- [x] 10.2 Update documentation (this file and proposal)
- [x] 10.3 Review deprecated migration files
  - **Decision**: Keep `2026_01_23_094150_add_central_purchasing_to_people_table.php` for historical record
  - **Reason**: Migration has already been run and is part of migration history
  - **Action**: Added deprecation comment to migration file
  - **Note**: The removal migration (`2026_01_29_043515_remove_central_purchasing_from_people_table.php`) properly reverses this migration's changes
