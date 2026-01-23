# Change: Add Authorization Checks to Livewire Components

## Why

Two critical ERP Livewire components bypass the authorization layer entirely, creating security vulnerabilities:

1. **QuotationEvaluationForm** - Creates QuotationEvaluation and KeyAccount records without checking if the user has permission
2. **SupplierQuoteComparison** - Modifies SupplierQuoteItem records via direct database queries without authorization

Policies exist (`QuotationEvaluationPolicy`, `KeyAccountPolicy`, `SupplierQuotePolicy`) but are not being used by these components. Any authenticated user can:
- Create quotation evaluations for any request in their team
- Create key accounts without `create key accounts` permission
- Modify supplier quote selections without `update supplier quotes` permission

## What Changes

### QuotationEvaluationForm Authorization

- **MODIFIED** `save()` method to check `QuotationEvaluationPolicy::create`
- **MODIFIED** `createKeyAccount()` method to check `KeyAccountPolicy::create`
- **MODIFIED** `saveNewKeyAccount()` method to check `KeyAccountPolicy::create`
- **ADDED** Request ownership validation before QE creation

### SupplierQuoteComparison Authorization

- **MODIFIED** `applySelections()` method to check `SupplierQuotePolicy::update` for each affected quote
- **MODIFIED** `selectSupplierForItem()` to validate quote belongs to user's team
- **MODIFIED** `selectSingleSupplier()` to validate quote belongs to user's team
- **ADDED** Team ownership validation on mount

### Authorization Trait

- **ADDED** `AuthorizesLivewireActions` trait for consistent authorization patterns
- Provides `authorizeAction()` method using Laravel's Gate
- Provides `ensureTeamOwnership()` method for multi-tenancy validation

### Test Coverage

- **ADDED** Feature tests for authorization in `QuotationEvaluationForm`
- **ADDED** Feature tests for authorization in `SupplierQuoteComparison`
- **ADDED** Tests for permission denial scenarios

## Impact

- Affected specs: `erp-trading-core`
- Affected code:
  - `app/Livewire/QuotationEvaluationForm.php` - Add authorization checks
  - `app/Livewire/SupplierQuoteComparison.php` - Add authorization checks
  - `app/Livewire/Concerns/AuthorizesLivewireActions.php` - New trait
  - `tests/Feature/Livewire/QuotationEvaluationFormTest.php` - New tests
  - `tests/Feature/Livewire/SupplierQuoteComparisonTest.php` - New tests

## Breaking Changes

None. Authorization is additive - users with correct permissions will continue to work normally. Users without permissions will now receive proper authorization errors instead of silently succeeding.

## Security Improvements

| Component | Method | Before | After |
|-----------|--------|--------|-------|
| QuotationEvaluationForm | `save()` | No auth check | Requires `create quotation evaluations` |
| QuotationEvaluationForm | `createKeyAccount()` | No auth check | Requires `create key accounts` |
| SupplierQuoteComparison | `applySelections()` | No auth check | Requires `update supplier quotes` |
| SupplierQuoteComparison | `selectSupplierForItem()` | No validation | Validates team ownership |
