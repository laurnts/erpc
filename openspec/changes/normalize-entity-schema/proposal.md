# Change: Normalize Entity Schema - Eliminate Duplications

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
5. `People` model lacks core fields (email, phone) - relies on custom fields

## What Changes

### Phase 1: Extend People Model
- **ADDED** `email`, `phone`, `job_title` columns to `people` table
- **ADDED** `is_key_account` boolean for approval workflow personnel
- **ADDED** `is_active` boolean for soft-disable without deletion

### Phase 2: Migrate KeyAccount → People
- **MIGRATED** existing `key_accounts` records to `people` with `is_key_account=true`
- **UPDATED** all `key_account_id` FKs to `people_id`
- **DEPRECATED** `key_accounts` table (keep for rollback, remove after validation)

### Phase 3: Normalize Approval Fields
- **ADDED** `dept_head_sales_id` FK to `quotation_evaluations` and `profit_and_losses`
- **ADDED** `deputy_director_id` FK to `quotation_evaluations` and `profit_and_losses`
- **ADDED** `approved_by_id` FK to `quotation_evaluations` and `profit_and_losses`
- **MIGRATED** existing string values to People records
- **DEPRECATED** `*_name` string columns

### Phase 4: Normalize Company Contact
- **ADDED** `contact_person_id` FK to `companies` table
- **MIGRATED** existing `contact_person` strings to People records
- **DEPRECATED** `contact_person` string column

### Phase 5: Type Contact Roles
- **ADDED** `ContactRole` enum (PRIMARY, BILLING, TECHNICAL, SALES, SUPPORT)
- **MODIFIED** `company_people.role` to use enum instead of free text

## Impact

- Affected specs: `crm-core`, `erp-trading-core`
- Affected code:
  - `app/Models/People.php` - extended fields
  - `app/Models/KeyAccount.php` - deprecated, becomes facade
  - `app/Models/Company.php` - new relationship
  - `app/Models/QuotationEvaluation.php` - new relationships
  - `app/Models/ProfitAndLoss.php` - new relationships
  - `app/Enums/ContactRole.php` - new enum
  - `app/Filament/Resources/KeyAccountResource.php` - becomes People filter
  - `database/migrations/` - 5 new migrations
  - All forms using KeyAccount selects

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
│  is_buyer, is_supplier, contact_person_id ───┐  │
└─────────────────────┬───────────────────────┬┘  │
                      │                       │    │
          company_people (role enum)          │    │
                      │                       │    │
                      ▼                       │    │
┌─────────────────────────────────────────────┼────┼──┐
│                    People                   │    │  │
│  name, email, phone, job_title              │    │  │
│  is_key_account, is_active                  │    │  │
│  ◄──────────────────────────────────────────┘    │  │
│  ◄───────────────────────────────────────────────┘  │
└────────────────────┬────────────────────────────────┘
                     │
    ┌────────────────┼────────────────┐
    │                │                │
    │ prepared_by_id │ approved_by_id │ dept_head_sales_id
    │                │                │ deputy_director_id
    ▼                ▼                ▼
┌─────────────────────────────────────────────────┐
│         QuotationEvaluation / ProfitAndLoss     │
└─────────────────────────────────────────────────┘
```

## Breaking Changes

- **KeyAccountResource** becomes a filtered view of PeopleResource
- **KeyAccount model** deprecated - use `People::where('is_key_account', true)`
- **API endpoints** returning KeyAccount will return People structure
- **Form selects** for KeyAccount will use People with filter

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
