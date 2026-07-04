# Change: Improve ERP Type Safety

## Why

The ERP module has 21 type safety issues across 11 files that reduce code reliability and IDE support:

1. **Missing PHPDoc generics** - Collections and arrays lack proper type documentation, breaking IDE autocomplete
2. **Unsafe casts** - Direct `(float)` and `(int)` casts without null/type validation can cause silent data corruption
3. **Untyped closure parameters** - Arrow functions in `map()`, `filter()` lack parameter types
4. **Missing array shape documentation** - Complex arrays passed between methods lack structure documentation
5. **Unsafe array key access** - Direct access to JSON `data` columns without validation

While the codebase correctly uses `declare(strict_types=1)` and `===` comparisons, these additional type safety improvements will:
- Enable PHPStan level 9 compliance
- Improve IDE autocomplete in nested structures
- Catch bugs at static analysis time instead of runtime
- Make code self-documenting

## What Changes

### PHPDoc Generic Annotations

- **MODIFIED** `SupplierQuoteComparison.php` - Add `@return Collection<int, SupplierQuote>` to computed properties
- **MODIFIED** `QuotationEvaluationForm.php` - Add generic types to query result variables
- **MODIFIED** `BuyerQuotesRelationManager.php` - Add array shape documentation to repeater state
- **MODIFIED** `SupplierQuotesRelationManager.php` - Add typed closures in relationship callbacks

### Safe Cast Helpers

- **ADDED** `app/Support/SafeCast.php` - Helper class for validated type casting
- **MODIFIED** `PdfGenerationService.php` - Use SafeCast for numeric operations
- **MODIFIED** `CreditLimitWarningService.php` - Use SafeCast for credit calculations
- **MODIFIED** Relation managers - Use SafeCast in `calculateItemTotals()`

### Typed Closure Parameters

- **MODIFIED** All `map()`, `filter()`, `mapWithKeys()` calls to include typed parameters
- **MODIFIED** Arrow functions to use explicit parameter types

### Array Shape Documentation

- **MODIFIED** `QuotationEvaluation::getItems()` - Add `@return array<int, array{id: int, description: string, ...}>`
- **MODIFIED** `QuotationEvaluation::getSuppliers()` - Add proper return type documentation
- **MODIFIED** `buildSnapshotData()` - Document snapshot array structure

### Safe Array Access

- **MODIFIED** JSON `data` column access to use null-safe operators and validation
- **ADDED** Helper methods for typed data extraction from JSON columns

## Impact

- Affected specs: `erp-trading-core`
- Affected code:
  - `app/Support/SafeCast.php` - New utility class
  - `app/Livewire/QuotationEvaluationForm.php` - PHPDoc additions
  - `app/Livewire/SupplierQuoteComparison.php` - PHPDoc additions
  - `app/Services/Erp/PdfGenerationService.php` - Safe casts
  - `app/Services/Erp/CreditLimitWarningService.php` - Safe casts
  - `app/Models/QuotationEvaluation.php` - Array shape docs, safe access
  - `app/Models/ProfitAndLoss.php` - Array shape docs, safe access
  - `app/Filament/Resources/RequestResource/RelationManagers/*.php` - Typed closures

## Breaking Changes

None. All changes are additive PHPDoc annotations or internal implementation improvements. No public API changes.

## Metrics

| Category | Issues | Files |
|----------|--------|-------|
| Missing PHPDoc generics | 8 | 5 |
| Unsafe casts | 6 | 4 |
| Untyped closures | 5 | 5 |
| Missing array shapes | 4 | 3 |
| Unsafe array access | 3 | 2 |
| **Total** | **26** | **11** |

## Re-scope Note (2026-07-04)

The January task list cited line numbers and an error inventory (26 issues) that no longer match the codebase after five months of drift (LineCalculator refactor, FinancialSnapshot, item-level fulfillment work). Re-derived from live PHPStan output at the project's configured level 7: 174 errors across the same file scope. Target revised from "level 9 compliance" to "zero errors at project level 7 within the ERP scope" — level escalation can be a follow-up change. tasks.md rewritten accordingly; the What Changes architecture (SafeCast utility, generics, array shapes, typed closures) is unchanged.
