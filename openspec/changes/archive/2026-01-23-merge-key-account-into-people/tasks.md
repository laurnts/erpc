# Tasks: Merge Key Account into People

## 1. Database Migrations
- [x] 1.1 Add `is_key_account` column to `people` table
- [x] 1.2 Create `key_account_people_mapping` table for data migration
- [x] 1.3 Migrate Key Account data to People with custom fields (email → emails tag, phone → phone_number)
  - Fixed: Added `creation_source = 'web'` to migration (required field)
- [x] 1.4 Update foreign keys in `quotation_evaluations.prepared_by_id` to reference `people`
- [x] 1.5 Update foreign keys in `profit_and_losses.prepared_by_id` to reference `people`
- [x] 1.6 Update foreign keys in `key_account_buyers.key_account_id` to reference `people`
- [x] 1.7 Drop `key_account_people_mapping` table
- [x] 1.8 Drop `key_accounts` table

## 2. Model Updates
- [x] 2.1 Add `is_key_account` to People model fillable and casts
- [x] 2.2 Add `buyers()` relationship to People model
- [x] 2.3 Add `preparedEvaluations()` relationship to People model
- [x] 2.4 Update QuotationEvaluation model `preparedBy()` relationship (KeyAccount → People)
- [x] 2.5 Update ProfitAndLoss model `preparedBy()` relationship (KeyAccount → People)
- [x] 2.6 Update Company model `keyAccounts()` relationship (KeyAccount → People, filtered by is_key_account)

## 3. Filament Resources
- [x] 3.1 Create `PeopleResource/RelationManagers/BuyersRelationManager`
- [x] 3.2 Add `is_key_account` toggle to PeopleResource form schema
- [x] 3.3 Update ViewPeople to conditionally show BuyersRelationManager when `is_key_account = true`
- [x] 3.4 Update BuyerQuotesRelationManager to use People instead of KeyAccount
- [x] 3.5 Update QuotationEvaluationResource to use People instead of KeyAccount
- [x] 3.6 Update ProfitAndLossResource to use People instead of KeyAccount
- [x] 3.7 Update QuotationEvaluationForm (Livewire) to use People instead of KeyAccount

## 4. Observers
- [x] 4.1 Create QuotationEvaluationObserver with team_id and creator_id auto-assignment
- [x] 4.2 Create ProfitAndLossObserver with team_id and creator_id auto-assignment
- [x] 4.3 Register QuotationEvaluationObserver in QuotationEvaluation model
- [x] 4.4 Register ProfitAndLossObserver in ProfitAndLoss model

## 5. Cleanup
- [x] 5.1 Delete KeyAccount model (`app/Models/KeyAccount.php`)
- [x] 5.2 Delete KeyAccountResource (`app/Filament/Resources/KeyAccountResource.php`)
- [x] 5.3 Delete KeyAccountResource pages (Create, Edit, List, View)
- [x] 5.4 Delete KeyAccountResource RelationManagers (BuyersRelationManager)
- [x] 5.5 Delete KeyAccountPolicy (`app/Policies/KeyAccountPolicy.php`)

## 6. Testing & Verification
- [x] 6.1 Run migrations on development database (completed successfully)
- [x] 6.2 Verify all Key Account records migrated to People with `is_key_account = true`
- [x] 6.3 Verify email data preserved in People custom field `emails` (tags input)
- [x] 6.4 Verify phone data preserved in People custom field `phone_number`
- [x] 6.5 Verify buyer assignments preserved for migrated Key Accounts
- [x] 6.6 Verify Quotation Evaluations still reference correct preparer (People)
- [x] 6.7 Verify Profit & Loss documents still reference correct preparer (People)
- [x] 6.8 Verify Buyers tab appears when viewing Key Account person (`is_key_account = true`)
- [x] 6.9 Verify Buyers tab does NOT appear for regular People (`is_key_account = false`)
- [x] 6.10 Test creating new Key Account via People form (toggle `is_key_account`)
- [x] 6.11 Test assigning buyers to Key Account person via Buyers tab
- [x] 6.12 Test creating QE with People as preparer (filtered by `is_key_account = true`)
- [x] 6.13 Test creating PNL with People as preparer (filtered by `is_key_account = true`)
- [x] 6.14 Verify all Filament resources load without errors
- [x] 6.15 Verify navigation no longer shows Key Accounts menu item

## 7. Documentation
- [x] 7.1 Create migration proposal document
