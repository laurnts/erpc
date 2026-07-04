# Implementation Tasks (re-scoped 2026-07-04)

Re-derived from live PHPStan (level 7) output: 174 errors in the change's file scope. Original line-number references were stale. Goal per cluster: drive the listed files to ZERO PHPStan errors at project level 7 with behavior-preserving changes only (PHPDoc generics, array shapes, typed closures, instanceof narrowing, SafeCast for unsafe casts). Existing tests must stay green throughout.

## 1. SafeCast Utility

- [x] 1.1 Create `app/Support/SafeCast.php`: `toFloat(mixed $value, float $default = 0.0): float`, `toInt(mixed $value, int $default = 0): int`, `toString(mixed $value, string $default = ''): string`, `toBool(mixed $value, bool $default = false): bool`, `toArray(mixed $value, array $default = []): array` (final class, static methods)
- [x] 1.2 Unit tests `tests/Unit/Support/SafeCastTest.php`: null, empty string, invalid type, valid passthrough for each method

## 2. Models & Services (21 errors → 0)

- [x] 2.1 Fix all PHPStan errors in: `QuotationEvaluation.php`, `ProfitAndLoss.php`, `BuyerQuote.php`, `BuyerQuoteItem.php`, `SupplierQuoteItem.php`, `BuyerOrderItem.php`, `PdfGenerationService.php`, `CreditLimitWarningService.php`, `TaxCalculationService.php`

## 3. Livewire Components (13 errors → 0)

- [x] 3.1 Fix all PHPStan errors in: `QuotationEvaluationForm.php`, `SupplierQuoteComparison.php`

## 4. SupplierQuotesRelationManager (75 errors → 0)

- [x] 4.1 Fix all PHPStan errors in `SupplierQuotesRelationManager.php`

## 5. BuyerQuotesRelationManager (43 errors → 0)

- [x] 5.1 Fix all PHPStan errors in `BuyerQuotesRelationManager.php`

## 6. SupplierOrdersRelationManager (22 errors → 0)

- [x] 6.1 Fix all PHPStan errors in `SupplierOrdersRelationManager.php`

## 7. Verification

- [x] 7.1 PHPStan on the full 14-file scope: 0 errors
- [x] 7.2 `php artisan test --compact tests/Feature/Erp tests/Unit` green; pint clean on touched files
