# Implementation Tasks

## 1. Extend People Model

- [ ] 1.1 Create migration to add columns to `people` table
  ```php
  $table->string('email')->nullable()->after('name');
  $table->string('phone', 50)->nullable()->after('email');
  $table->string('job_title')->nullable()->after('phone');
  $table->boolean('is_key_account')->default(false)->after('job_title');
  $table->boolean('is_active')->default(true)->after('is_key_account');
  $table->index(['team_id', 'is_key_account']);
  $table->index(['team_id', 'email']);
  ```
- [ ] 1.2 Update `People` model with new fillable fields
- [ ] 1.3 Update `People` model with new casts
- [ ] 1.4 Add `scopeKeyAccounts()` to People model
- [ ] 1.5 Add `scopeActiveKeyAccounts()` to People model
- [ ] 1.6 Update `PeopleResource` form to include email, phone, job_title fields
- [ ] 1.7 Write tests for new People fields

## 2. Create ContactRole Enum

- [ ] 2.1 Create `app/Enums/ContactRole.php`
  - Cases: `PRIMARY`, `BILLING`, `TECHNICAL`, `SALES`, `SUPPORT`, `OTHER`
  - Implement `HasLabel`, `HasDescription` contracts
- [ ] 2.2 Create migration to change `company_people.role` column type
- [ ] 2.3 Migrate existing role strings to enum values
- [ ] 2.4 Update `Company` model pivot definition
- [ ] 2.5 Update `People` model pivot definition
- [ ] 2.6 Update forms to use enum select for role
- [ ] 2.7 Write tests for ContactRole enum

## 3. Migrate KeyAccount to People

- [ ] 3.1 Create migration to insert KeyAccounts into People
  ```sql
  INSERT INTO people (team_id, name, email, phone, is_key_account, is_active, creator_id, created_at, updated_at)
  SELECT team_id, name, email, phone, true, is_active, creator_id, created_at, updated_at
  FROM key_accounts
  ```
- [ ] 3.2 Create mapping table `key_account_people_map` for FK updates
- [ ] 3.3 Update `prepared_by_id` FKs in `quotation_evaluations`
- [ ] 3.4 Update `key_account_buyers` pivot to use `people_id`
- [ ] 3.5 Create `KeyAccount` facade model that proxies to People
- [ ] 3.6 Update `KeyAccountResource` to filter People with `is_key_account=true`
- [ ] 3.7 Update `KeyAccountObserver` → `PeopleObserver` (or remove if redundant)
- [ ] 3.8 Update all `KeyAccount::create()` calls to `People::create(['is_key_account' => true])`
- [ ] 3.9 Write tests for KeyAccount → People migration

## 4. Normalize Approval Fields

- [ ] 4.1 Create migration to add FK columns to `quotation_evaluations`
  ```php
  $table->foreignId('dept_head_sales_id')->nullable()->constrained('people')->nullOnDelete();
  $table->foreignId('deputy_director_id')->nullable()->constrained('people')->nullOnDelete();
  $table->foreignId('approved_by_id')->nullable()->constrained('people')->nullOnDelete();
  ```
- [ ] 4.2 Create migration to add FK columns to `profit_and_losses`
- [ ] 4.3 Create data migration to convert string names to People records
  - Match by name within team
  - Create new People record if no match
  - Set `is_key_account=true` for created records
- [ ] 4.4 Update `QuotationEvaluation` model with new relationships
- [ ] 4.5 Update `ProfitAndLoss` model with new relationships
- [ ] 4.6 Update `QuotationEvaluationResource` form to use People selects
- [ ] 4.7 Update `ProfitAndLossResource` form to use People selects
- [ ] 4.8 Update `BuyerQuotesRelationManager` PNL creation form
- [ ] 4.9 Update `QuotationEvaluationForm` Livewire component
- [ ] 4.10 Update PDF templates to use relationship instead of string
- [ ] 4.11 Mark `*_name` columns as deprecated in model docblocks
- [ ] 4.12 Write tests for approval field relationships

## 5. Normalize Company Contact Person

- [ ] 5.1 Create migration to add `contact_person_id` to `companies`
  ```php
  $table->foreignId('contact_person_id')->nullable()->constrained('people')->nullOnDelete();
  ```
- [ ] 5.2 Create data migration to convert `contact_person` strings to People
  - Match by name within team and company's people
  - Create new People record if no match
  - Link to company via `company_people` pivot
- [ ] 5.3 Update `Company` model with `contactPerson()` relationship
- [ ] 5.4 Update `CompanyResource` form to use People select
- [ ] 5.5 Update `BuyerResource` form to use People select
- [ ] 5.6 Update `SupplierResource` form to use People select
- [ ] 5.7 Mark `contact_person` string column as deprecated
- [ ] 5.8 Write tests for contact person relationship

## 6. Update Form Components

- [ ] 6.1 Create `KeyAccountSelect` form component
  - Filters People by `is_key_account=true`
  - Supports inline creation with `is_key_account=true` default
- [ ] 6.2 Create `ApprovalPersonnelSchema` form component
  - Uses `KeyAccountSelect` for all approval fields
  - Replaces `CentralPurchasingSchema` from other proposal
- [ ] 6.3 Update all forms using KeyAccount selects to use new components
- [ ] 6.4 Remove deprecated KeyAccount-specific components

## 7. Cleanup and Deprecation

- [ ] 7.1 Add `@deprecated` annotations to `KeyAccount` model
- [ ] 7.2 Add `@deprecated` annotations to string columns in models
- [ ] 7.3 Create future migration to drop deprecated columns (disabled by default)
- [ ] 7.4 Create future migration to drop `key_accounts` table (disabled by default)
- [ ] 7.5 Update CLAUDE.md with new entity relationships
- [ ] 7.6 Document migration path for API consumers

## 8. Write Tests

- [ ] 8.1 Create `tests/Feature/PeopleExtendedTest.php`
  - Test new fields work correctly
  - Test key account scope
  - Test email uniqueness per team
- [ ] 8.2 Create `tests/Feature/KeyAccountMigrationTest.php`
  - Test data migration integrity
  - Test FK updates work correctly
  - Test KeyAccount facade works
- [ ] 8.3 Create `tests/Feature/ApprovalFieldsTest.php`
  - Test QE with People relationships
  - Test PNL with People relationships
  - Test name → People conversion
- [ ] 8.4 Create `tests/Feature/ContactPersonTest.php`
  - Test Company with contact_person_id
  - Test migration from string to FK
- [ ] 8.5 Create `tests/Unit/Enums/ContactRoleTest.php`

## Summary

| Phase | Tasks | Database Changes |
|-------|-------|------------------|
| 1. Extend People | 7 | 1 migration (+5 columns) |
| 2. ContactRole Enum | 7 | 1 migration (alter column) |
| 3. Migrate KeyAccount | 9 | 2 migrations (data + FK) |
| 4. Normalize Approval | 12 | 2 migrations (+6 columns) |
| 5. Normalize Contact | 8 | 2 migrations (+1 column) |
| 6. Form Components | 4 | None |
| 7. Cleanup | 6 | 2 future migrations |
| 8. Tests | 5 | None |

**Total: 58 tasks, 10 migrations**

## Migration Order

```
1. YYYY_MM_DD_100000_add_extended_fields_to_people_table.php
2. YYYY_MM_DD_100001_create_contact_role_enum.php
3. YYYY_MM_DD_100002_migrate_key_accounts_to_people.php
4. YYYY_MM_DD_100003_update_key_account_foreign_keys.php
5. YYYY_MM_DD_100004_add_approval_fks_to_quotation_evaluations.php
6. YYYY_MM_DD_100005_add_approval_fks_to_profit_and_losses.php
7. YYYY_MM_DD_100006_migrate_approval_names_to_people.php
8. YYYY_MM_DD_100007_add_contact_person_id_to_companies.php
9. YYYY_MM_DD_100008_migrate_contact_person_to_people.php
10. YYYY_MM_DD_999999_drop_deprecated_columns.php (disabled)
```
