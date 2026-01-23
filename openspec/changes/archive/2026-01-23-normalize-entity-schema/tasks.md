# Implementation Tasks

## 1. Extend People Model ✅ COMPLETED

- [x] 1.1 Create migration to add `is_key_account` column to `people` table ✅
- [x] 1.2 Update `People` model with `is_key_account` fillable ✅
- [x] 1.3 Update `People` model with `is_key_account` cast ✅
- [x] 1.4 Email/phone/job_title handled via custom fields (PeopleField enum) ✅
- [x] 1.5 `is_active` handled via SoftDeletes trait ✅
- [x] 1.6 PeopleResource already supports custom fields ✅
- [x] 1.7 Create migration to add `is_central_purchasing` and `central_purchasing_role` ✅
- [x] 1.8 Create `CentralPurchasingRole` enum ✅
- [x] 1.9 Migrate existing `is_key_account=true` to `is_central_purchasing=true` with role ✅
- [x] 1.10 Update PeopleResource form with Central Purchasing toggle and role select ✅
- [x] 1.11 Update ViewPeople page to conditionally show Buyer tab based on role ✅
- [ ] 1.12 Write tests for Central Purchasing functionality (optional)

## 2. Create ContactRole Enum ✅ COMPLETED

- [x] 2.1 Create `app/Enums/ContactRole.php` ✅
  - Cases: `PRIMARY`, `BILLING`, `TECHNICAL`, `SALES`, `SUPPORT`, `OTHER`
  - Implement `HasLabel`, `HasDescription` contracts
- [x] 2.2 Create migration to change `company_people.role` column type ✅
- [x] 2.3 Migrate existing role strings to enum values ✅ (No existing data to migrate)
- [x] 2.4 Update `Company` model pivot definition ✅ (Created CompanyPeople pivot model)
- [x] 2.5 Update `People` model pivot definition ✅ (Using CompanyPeople pivot model)
- [x] 2.6 Update forms to use enum select for role ✅ (Updated PeopleRelationManager)
- [ ] 2.7 Write tests for ContactRole enum (optional)

## 3. Migrate KeyAccount to People ✅ COMPLETED

- [x] 3.1 Create migration to insert KeyAccounts into People ✅
- [x] 3.2 Create mapping table `key_account_people_mapping` for FK updates ✅
- [x] 3.3 Update `prepared_by_id` FKs in `quotation_evaluations` ✅
- [x] 3.4 `key_account_buyers` pivot already uses `key_account_id` (works with People via FK) ✅
- [x] 3.5 KeyAccount model removed (no facade needed) ✅
- [x] 3.6 KeyAccountResource removed ✅
- [x] 3.7 KeyAccountObserver removed ✅
- [x] 3.8 All KeyAccount usage migrated to People ✅
- [ ] 3.9 Write tests for KeyAccount → People migration (optional)

## 4. Normalize Approval Fields ✅ COMPLETED

- [x] 4.1 Create migration to add FK columns to `quotation_evaluations` ✅
  ```php
  $table->foreignId('dept_head_sales_id')->nullable()->constrained('people')->nullOnDelete();
  $table->foreignId('deputy_director_id')->nullable()->constrained('people')->nullOnDelete();
  $table->foreignId('approved_by_id')->nullable()->constrained('people')->nullOnDelete();
  ```
- [x] 4.2 Create migration to add FK columns to `profit_and_losses` ✅
- [x] 4.3 Create data migration to convert string names to People records ✅
  - Match by name within team
  - Create new People record if no match
  - Set `is_central_purchasing=true` and `central_purchasing_role` for created records
- [x] 4.4 Update `QuotationEvaluation` model with new relationships ✅
- [x] 4.5 Update `ProfitAndLoss` model with new relationships ✅
- [x] 4.6 Update `QuotationEvaluationResource` form to use People selects ✅
- [x] 4.7 Update `ProfitAndLossResource` form to use People selects ✅
- [x] 4.8 Update `BuyerQuotesRelationManager` PNL creation form ✅
- [x] 4.9 Update `QuotationEvaluationForm` Livewire component ✅
- [x] 4.10 Update PDF templates to use relationship instead of string ✅
- [x] 4.11 Mark `*_name` columns as deprecated in model docblocks ✅
- [ ] 4.12 Write tests for approval field relationships (optional)

## 5. Normalize Company Contact Person ❌ NOT NEEDED

**Reason:** The `company_people` pivot table with `is_primary` flag already provides this functionality. The `Company::primaryContact()` relationship uses the pivot, and `PeopleRelationManager` allows setting primary contacts. The `contact_person` string field is unused (0 records in database, not in any forms).

**Alternative:** Use `$company->primaryContact()->first()` or `$company->people()->wherePivot('is_primary', true)->first()` instead of a direct FK.

- [x] ~~5.1 Create migration to add `contact_person_id` to `companies`~~ ❌ Not needed
- [x] ~~5.2 Create data migration to convert `contact_person` strings to People~~ ❌ No data exists
- [x] ~~5.3 Update `Company` model with `contactPerson()` relationship~~ ❌ Use `primaryContact()` instead
- [x] ~~5.4 Update `CompanyResource` form to use People select~~ ❌ Already handled via pivot
- [x] ~~5.5 Update `BuyerResource` form to use People select~~ ❌ Already handled via pivot
- [x] ~~5.6 Update `SupplierResource` form to use People select~~ ❌ Already handled via pivot
- [ ] 5.7 Mark `contact_person` string column as deprecated (optional cleanup)
- [x] ~~5.8 Write tests for contact person relationship~~ ❌ Not needed

## 6. Update Form Components ✅ COMPLETED

- [x] 6.1 Create `KeyAccountSelect` form component ✅
  - Filters People by `is_central_purchasing=true` and `central_purchasing_role`
  - Supports inline creation with `is_central_purchasing=true` and role default
  - Accepts `int|callable|null` for `$buyerId` to support dynamic filtering
- [x] 6.2 Create `ApprovalPersonnelSchema` form component ✅
  - Uses `KeyAccountSelect` for all approval fields with role-specific filtering
  - Filters by `CentralPurchasingRole` (KEY_ACCOUNT, DEPT_HEAD_SALES, DEPUTY_DIRECTOR, DIRECTOR)
  - Replaces `CentralPurchasingSchema` (kept for backward compatibility)
- [x] 6.3 Update all forms using KeyAccount selects to use new components ✅
  - Updated `QuotationEvaluationResource` to use `ApprovalPersonnelSchema`
  - Updated `ProfitAndLossResource` to use `ApprovalPersonnelSchema`
  - Updated `BuyerQuotesRelationManager` PNL creation form
- [x] 6.4 Remove deprecated KeyAccount-specific components ✅
  - Removed `KeyAccountObserver` (no longer needed)
- [x] 6.5 Fix `ApprovalPersonnelSchema` class loading issue (split into separate file) ✅

## 7. Cleanup and Deprecation ✅ COMPLETED

- [x] 7.1 ~~Add `@deprecated` annotations to `KeyAccount` model~~ ✅ (KeyAccount model removed)
- [x] 7.2 Add `@deprecated` annotations to string columns in models ✅
  - `QuotationEvaluation`: `dept_head_sales_name`, `deputy_director_name`, `approved_by_name`
  - `ProfitAndLoss`: `dept_head_sales_name`, `deputy_director_name`, `approved_by_name`
  - `Company`: `contact_person`
- [x] 7.3 Create future migration to drop deprecated columns ✅
  - `2026_01_23_092703_drop_deprecated_columns.php` (disabled by default)
- [x] 7.4 ~~Create future migration to drop `key_accounts` table~~ ✅ (Already dropped in Phase 2)
- [x] 7.5 Update documentation with new entity relationships ✅
- [x] 7.6 Document migration path ✅ (included in proposal.md)

## 8. Write Tests ✅ COMPLETED

- [x] 8.1 Create `tests/Feature/PeopleExtendedTest.php` ✅
  - Test new fields work correctly
  - Test key account scope
  - Test team relationships
- [x] 8.2 ~~Create `tests/Feature/KeyAccountMigrationTest.php`~~ ✅ (Not needed - KeyAccount removed)
- [x] 8.3 Create `tests/Feature/ApprovalFieldsTest.php` ✅
  - Test QE with People relationships
  - Test PNL with People relationships
  - Test nullable relationships
- [x] 8.4 ~~Create `tests/Feature/ContactPersonTest.php`~~ ✅ (Not needed - Phase 4 skipped)
- [x] 8.5 Create `tests/Unit/Enums/ContactRoleTest.php` ✅
  - Test enum cases and values
  - Test HasLabel and HasDescription contracts
  - Test from/tryFrom methods

## Summary

| Phase | Tasks | Database Changes | Status |
|-------|-------|------------------|--------|
| 1. Extend People | 12 | 2 migrations (+5 columns, then +2 columns) | ✅ COMPLETED |
| 2. ContactRole Enum | 7 | 1 migration (alter column) | ✅ COMPLETED |
| 3. Migrate KeyAccount | 9 | 2 migrations (data + FK) | ✅ COMPLETED |
| 4. Normalize Approval | 12 | 3 migrations (+6 columns) | ✅ COMPLETED |
| 5. Normalize Contact | 8 | 2 migrations (+1 column) | ❌ NOT NEEDED |
| 6. Form Components | 5 | None | ✅ COMPLETED |
| 7. Cleanup | 6 | 1 future migration | ✅ COMPLETED |
| 8. Tests | 5 | None | ✅ COMPLETED |

**Total: 64 tasks, 10 migrations (all completed)**

## Migration Order

### Completed Migrations ✅
```
1. YYYY_MM_DD_100000_add_extended_fields_to_people_table.php ✅
2. YYYY_MM_DD_100002_migrate_key_accounts_to_people.php ✅
3. YYYY_MM_DD_100003_update_key_account_foreign_keys.php ✅
4. 2026_01_23_050000_add_approval_fks_to_quotation_evaluations.php ✅
5. 2026_01_23_050001_add_approval_fks_to_profit_and_losses.php ✅
6. 2026_01_23_050002_migrate_approval_names_to_people.php ✅
7. 2026_01_23_050003_fix_invalid_delivery_type_values.php ✅ (bonus fix)
8. 2026_01_23_091313_change_company_people_role_to_enum.php ✅
9. 2026_01_23_094150_add_central_purchasing_to_people_table.php ✅
10. 2026_01_23_092703_drop_deprecated_columns.php ✅ (disabled by default, future cleanup)
```

### Pending Migrations ⏳
```
None - all migrations completed
```
