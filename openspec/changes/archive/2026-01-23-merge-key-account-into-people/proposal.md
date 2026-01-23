# Change: Merge Key Account Master Data into People

## Why

The Key Account master data is redundant with the People master data. Both represent personnel/contacts, but Key Accounts have additional functionality (buyer assignment). By merging Key Accounts into People with an `is_key_account` flag, we:

- **Reduce data duplication**: Single source of truth for all personnel
- **Simplify data model**: One less table and model to maintain
- **Improve consistency**: All contacts use the same custom fields system (emails, phone, job title)
- **Maintain functionality**: Key Account features (buyer assignment) preserved via conditional UI

## What Changes

### Database Changes

1. **Add `is_key_account` flag to `people` table**
   - Boolean column, default `false`
   - Indexed for performance

2. **Migrate Key Account data to People**
   - Copy all `key_accounts` records to `people` table
   - Set `is_key_account = true` for migrated records
   - Set `creation_source = 'web'` (required field, default for migrated records)
   - Migrate `email` → People custom field `emails` (tags input, JSON array)
   - Migrate `phone` → People custom field `phone_number` (text)
   - Preserve `team_id`, `creator_id`, `created_at`, `updated_at`

3. **Update foreign key references**
   - `quotation_evaluations.prepared_by_id`: `key_accounts` → `people`
   - `profit_and_losses.prepared_by_id`: `key_accounts` → `people`
   - `key_account_buyers.key_account_id`: `key_accounts` → `people` (column name unchanged)

4. **Drop `key_accounts` table**
   - After all foreign keys updated and data migrated

### Model Changes

1. **People Model**
   - Add `is_key_account` to fillable and casts
   - Add `buyers()` relationship (when `is_key_account = true`)
   - Add `preparedEvaluations()` relationship

2. **QuotationEvaluation Model**
   - Change `preparedBy()` relationship: `KeyAccount` → `People`

3. **ProfitAndLoss Model**
   - Change `preparedBy()` relationship: `KeyAccount` → `People`

4. **Company Model**
   - Change `keyAccounts()` relationship: `KeyAccount` → `People` (filtered by `is_key_account = true`)

### Filament Resource Changes

1. **PeopleResource**
   - Add `is_key_account` toggle in form schema
   - Conditionally show `BuyersRelationManager` tab when viewing Key Account person

2. **Create `PeopleResource/RelationManagers/BuyersRelationManager`**
   - Copy from `KeyAccountResource/RelationManagers/BuyersRelationManager`
   - Update to use `People` model and `people_buyers` pivot

3. **Update all KeyAccount references**
   - `BuyerQuotesRelationManager`: Use `People::where('is_key_account', true)`
   - `QuotationEvaluationResource`: Use `People` instead of `KeyAccount`
   - `ProfitAndLossResource`: Use `People` instead of `KeyAccount`
   - `QuotationEvaluationForm` (Livewire): Use `People` instead of `KeyAccount`

### Files Deleted

- `app/Models/KeyAccount.php`
- `app/Filament/Resources/KeyAccountResource.php`
- `app/Filament/Resources/KeyAccountResource/Pages/*` (all pages)
- `app/Filament/Resources/KeyAccountResource/RelationManagers/BuyersRelationManager.php`
- `app/Policies/KeyAccountPolicy.php`

## Impact

- **Affected specs**: `erp-quoting`, `erp-master-data`
- **Breaking changes**: None (data migration preserves all relationships)
- **Migration risk**: Medium (requires careful foreign key updates)
- **User impact**: Minimal (UI remains the same, Key Accounts now appear in People list)

## Migration Strategy

### Phase 1: Preparation
1. Add `is_key_account` column to `people` table
2. Create temporary mapping table `key_account_people_mapping`

### Phase 2: Data Migration
1. Migrate all `key_accounts` records to `people`
2. Set custom field values for email and phone
3. Store mapping in `key_account_people_mapping`

### Phase 3: Foreign Key Updates
1. Update `quotation_evaluations.prepared_by_id` using mapping
2. Update `profit_and_losses.prepared_by_id` using mapping
3. Update `key_account_buyers.key_account_id` using mapping

### Phase 4: Cleanup
1. Drop `key_account_people_mapping` table
2. Drop `key_accounts` table

## Rollback Plan

The migration includes a `down()` method that:
1. Recreates `key_accounts` table structure
2. Restores data from `people` table (filtered by `is_key_account = true`)
3. Reverts custom fields back to direct columns
4. Restores foreign key references

## Testing Checklist

- [x] Migration runs successfully on development database
- [x] All Key Account data appears in People list with `is_key_account = true`
- [x] Email and phone data preserved in custom fields
- [x] Buyer assignments preserved for migrated Key Accounts
- [x] Quotation Evaluations still reference correct preparer
- [x] Profit & Loss documents still reference correct preparer
- [x] Buyers tab appears when viewing Key Account person
- [x] Can create new Key Account via People form
- [x] Can assign buyers to Key Account person
- [x] All Filament resources load without errors

## Migration Notes

**Issue Fixed:** The migration initially failed because `creation_source` is a required field in the `people` table. Fixed by adding `'creation_source' => 'web'` to the insert statement for migrated records.

## Related Changes

- **Observers**: Create `QuotationEvaluationObserver` and `ProfitAndLossObserver` (see multi-tenancy.md)
- **Tests**: Add tests for People model with `is_key_account` flag
- **Documentation**: Update API docs to reflect People model changes
