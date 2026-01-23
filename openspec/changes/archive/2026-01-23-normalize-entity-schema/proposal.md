# Change: Normalize Entity Schema - Eliminate Duplications

## Status Summary

**Completed Phases:**
- ✅ Phase 1: Extend People Model
- ✅ Phase 2: Migrate KeyAccount → People
- ✅ Phase 3: Normalize Approval Fields
- ✅ Phase 5: Type Contact Roles

**Pending Phases:**
- None (all critical phases completed)

**Not Needed:**
- ❌ Phase 4: Normalize Company Contact Person (functionality already provided by `company_people` pivot with `is_primary` flag)

**Bonus Fixes:**
- ✅ Fixed invalid `DeliveryType` enum values in `companies` table
- ✅ Created `SafeEnumCast` for graceful handling of invalid enum values
- ✅ Enhanced delivery type selection UI with full descriptions
- ✅ Replaced `is_key_account` with `is_central_purchasing` and `central_purchasing_role` enum for granular role-based filtering

## Why

The database schema has entity duplication and denormalization issues that cause:
- **Data inconsistency**: Same person can exist in multiple tables with different data
- **Referential integrity gaps**: String fields instead of FKs prevent data linking
- **Query complexity**: Can't easily answer "who approved the most documents?"
- **Maintenance burden**: Updates need to happen in multiple places

Key issues identified:
1. `KeyAccount` duplicates `People` (name, email, phone)
2. Approval fields use strings instead of foreign keys (inconsistent with `prepared_by_id`)
3. `Company.contact_person` is a string, not a relationship
4. `company_people.role` is free text, not typed
5. ~~`People` model lacks core fields (email, phone)~~ - **RESOLVED**: Email/phone are custom fields (architectural decision)

## What Changes

### Phase 1: Extend People Model ✅ COMPLETED
- **ADDED** `is_key_account` boolean column to `people` table for approval workflow personnel (later replaced)
- **ADDED** `is_central_purchasing` boolean and `central_purchasing_role` enum to `people` table ✅
- **CREATED** `CentralPurchasingRole` enum (KEY_ACCOUNT, DEPT_HEAD_SALES, DEPUTY_DIRECTOR, DIRECTOR) ✅
- **MIGRATED** existing `is_key_account=true` records to `is_central_purchasing=true` with `central_purchasing_role=KEY_ACCOUNT` ✅
- **DEPRECATED** `is_key_account` (kept for backward compatibility, will be dropped in future migration)
- **NOTE:** `email`, `phone`, `job_title` are stored as custom fields (not schema columns) via `PeopleField` enum
- **NOTE:** `is_active` is handled via `SoftDeletes` trait (not a separate boolean column)

### Phase 2: Migrate KeyAccount → People ✅ COMPLETED
- **MIGRATED** existing `key_accounts` records to `people` with `is_key_account=true` (later migrated to `is_central_purchasing=true`)
- **MIGRATED** email/phone from KeyAccount to People custom fields
- **UPDATED** all `key_account_id` FKs to `people_id`
- **DEPRECATED** `key_accounts` table (dropped via migration)

### Phase 3: Normalize Approval Fields ✅ COMPLETED
- **ADDED** `dept_head_sales_id` FK to `quotation_evaluations` and `profit_and_losses` ✅
- **ADDED** `deputy_director_id` FK to `quotation_evaluations` and `profit_and_losses` ✅
- **ADDED** `approved_by_id` FK to `quotation_evaluations` and `profit_and_losses` ✅
- **MIGRATED** existing string values to People records ✅
- **DEPRECATED** `*_name` string columns (marked in docblocks) ✅
- **UPDATED** all forms, Livewire components, and PDF templates to use relationships ✅

**Migrations Created:**
- `2026_01_23_050000_add_approval_fks_to_quotation_evaluations.php`
- `2026_01_23_050001_add_approval_fks_to_profit_and_losses.php`
- `2026_01_23_050002_migrate_approval_names_to_people.php`
- `2026_01_23_050003_fix_invalid_delivery_type_values.php` (bonus fix)
- `2026_01_23_091313_change_company_people_role_to_enum.php` (Phase 5)
- `2026_01_23_094150_add_central_purchasing_to_people_table.php` (Central Purchasing)

### Phase 4: Normalize Company Contact ❌ NOT NEEDED

**Status:** Skipped - functionality already provided by `company_people` pivot table.

**Reason:** 
- The `company_people` pivot table with `is_primary` flag already handles primary contacts
- `Company::primaryContact()` relationship uses the pivot: `$company->people()->wherePivot('is_primary', true)`
- `PeopleRelationManager` allows setting primary contacts via UI
- `contact_person` string field is unused (0 records in database, not in any forms)
- No migration needed - use pivot-based approach instead

**Alternative Approach:**
```php
// Get primary contact via pivot (already implemented)
$primaryContact = $company->primaryContact()->first();
// or
$primaryContact = $company->people()->wherePivot('is_primary', true)->first();
```

### Phase 5: Type Contact Roles ✅ COMPLETED
- **ADDED** `ContactRole` enum (PRIMARY, BILLING, TECHNICAL, SALES, SUPPORT, OTHER) ✅
- **MODIFIED** `company_people.role` to use enum instead of free text ✅
- **CREATED** `CompanyPeople` pivot model with enum casting ✅
- **UPDATED** `PeopleRelationManager` to include role selection and display ✅

**Migrations Created:**
- `2026_01_23_091313_change_company_people_role_to_enum.php`

## Impact

- Affected specs: `crm-core`, `erp-trading-core`
- Affected code:
  - `app/Models/People.php` - extended fields ✅
  - `app/Models/KeyAccount.php` - deprecated, removed ✅
  - `app/Models/Company.php` - SafeEnumCast for delivery_type ✅
  - `app/Models/QuotationEvaluation.php` - new relationships ✅
  - `app/Models/ProfitAndLoss.php` - new relationships ✅
  - `app/Enums/ContactRole.php` - new enum ✅
  - `app/Enums/CentralPurchasingRole.php` - new enum for Central Purchasing roles ✅
  - `app/Models/CompanyPeople.php` - pivot model with enum casting ✅
  - `app/Filament/Resources/KeyAccountResource.php` - removed ✅
  - `app/Filament/Resources/PeopleResource.php` - updated with Central Purchasing toggle and role select ✅
  - `app/Filament/Resources/PeopleResource/Pages/ViewPeople.php` - conditional Buyer tab display ✅
  - `app/Filament/Resources/QuotationEvaluationResource.php` - updated forms with ApprovalPersonnelSchema ✅
  - `app/Filament/Resources/ProfitAndLossResource.php` - updated forms with ApprovalPersonnelSchema ✅
  - `app/Filament/Resources/SupplierResource.php` - enhanced delivery type selection ✅
  - `app/Filament/Forms/Components/KeyAccountSelect.php` - filters by `is_central_purchasing` and `central_purchasing_role` ✅
  - `app/Filament/Forms/Components/ApprovalPersonnelSchema.php` - reusable schema component ✅
  - `app/Filament/Forms/Components/CentralPurchasingSchema.php` - deprecated wrapper ✅
  - `app/Livewire/QuotationEvaluationForm.php` - updated to use People selects ✅
  - `app/Casts/SafeEnumCast.php` - new cast for invalid enum handling ✅
  - `app/Casts/SafeDeliveryTypeCast.php` - specific cast for DeliveryType ✅
  - `database/migrations/` - 10 migrations created (3 for Phase 3, 1 for Phase 5, 1 for Central Purchasing, 1 bonus fix, 1 cleanup)
  - PDF templates updated to use relationships ✅

## Entity Relationship Diagram (After)

```
┌─────────────────┐         ┌─────────────────┐
│      Team       │         │      User       │
└────────┬────────┘         └────────┬────────┘
         │                           │
         │ team_id                   │ account_owner_id
         │                           │ creator_id
         ▼                           ▼
┌─────────────────────────────────────────────────┐
│                    Company                       │
│  is_buyer, is_supplier                           │
│  (contact_person: ❌ NOT NEEDED - use pivot)     │
└─────────────────────┬───────────────────────┬┘  │
                      │                       │    │
          company_people (role: ✅ ContactRole enum)  │    │
                      │  (is_primary flag)    │    │
                      │                       │    │
                      ▼                       │    │
┌─────────────────────────────────────────────┼────┼──┐
│                    People                   │    │  │
│  name, is_central_purchasing ✅              │    │  │
│  central_purchasing_role (enum) ✅            │    │  │
│  email/phone/job_title (custom fields) ✅    │    │  │
│  ◄──────────────────────────────────────────┘    │  │
│  ◄───────────────────────────────────────────────┘  │
└────────────────────┬────────────────────────────────┘
                     │
    ┌────────────────┼────────────────┐
    │                │                │
    │ prepared_by_id │ approved_by_id │ dept_head_sales_id ✅
    │                │                │ deputy_director_id ✅
    ▼                ▼                ▼
┌─────────────────────────────────────────────────┐
│         QuotationEvaluation / ProfitAndLoss     │
│         (All approval FKs: ✅ COMPLETED)        │
└─────────────────────────────────────────────────┘
```

## Breaking Changes

- **KeyAccountResource** removed - use PeopleResource with Central Purchasing filter
- **KeyAccount model** deprecated - use `People::where('is_central_purchasing', true)->where('central_purchasing_role', CentralPurchasingRole::KEY_ACCOUNT)`
- **`is_key_account`** deprecated - use `is_central_purchasing` and `central_purchasing_role` instead
- **API endpoints** returning KeyAccount will return People structure
- **Form selects** for KeyAccount will use People filtered by `is_central_purchasing` and `central_purchasing_role`

## Migration Strategy

1. **Additive first**: Add new columns/tables without removing old
2. **Dual-write**: Write to both old and new during transition
3. **Backfill**: Migrate historical data
4. **Validate**: Verify data integrity
5. **Deprecate**: Mark old columns as deprecated
6. **Remove**: Drop old columns in future release

## Rollback Plan

- Keep `key_accounts` table for 2 releases
- Keep `*_name` columns for 2 releases
- Migration `down()` methods restore original state
- Feature flag to switch between old/new behavior
