# Implementation Tasks

## 1. Create SafeCast Utility

- [ ] 1.1 Create `app/Support/SafeCast.php`
  - Add `toFloat(mixed $value, float $default = 0.0): float`
  - Add `toInt(mixed $value, int $default = 0): int`
  - Add `toString(mixed $value, string $default = ''): string`
  - Add `toBool(mixed $value, bool $default = false): bool`
  - Add `toArray(mixed $value, array $default = []): array`
- [ ] 1.2 Write unit tests `tests/Unit/Support/SafeCastTest.php`
  - Test null handling
  - Test empty string handling
  - Test invalid type handling
  - Test valid value passthrough

## 2. Update Services with Safe Casts

- [ ] 2.1 Update `app/Services/Erp/PdfGenerationService.php`
  - Line 29: Replace `(float) $item->line_total` with `SafeCast::toFloat($item->line_total)`
  - Line 41-56: Replace all explicit casts with SafeCast calls
  - Add PHPDoc to closure parameters in `filter()` and `map()`
- [ ] 2.2 Update `app/Services/Erp/CreditLimitWarningService.php`
  - Line 36-38: Replace `(float) $buyer->credit_limit` with SafeCast
  - Line 126, 131-137: Validate floats before `number_format()`
- [ ] 2.3 Update `app/Services/Erp/TaxCalculationService.php`
  - Add PHPDoc generics to method parameters
  - Type closure parameters

## 3. Update Models with Array Shape Documentation

- [ ] 3.1 Update `app/Models/QuotationEvaluation.php`
  - Add `@return` array shape to `getItems()` method
  - Add `@return` array shape to `getSuppliers()` method
  - Add safe null checks to data array access
  - Document `buildSnapshotData()` return structure
- [ ] 3.2 Update `app/Models/ProfitAndLoss.php`
  - Add `@return` array shape to data accessor methods
  - Add safe null checks to data array access
- [ ] 3.3 Update `app/Models/BuyerQuote.php`
  - Line 282-286: Add type assertion after `replicate()`
  - Line 419-420: Validate `preg_match()` result before array access

## 4. Update Livewire Components

- [ ] 4.1 Update `app/Livewire/SupplierQuoteComparison.php`
  - Line 40: Document `$itemSelections` as `@var array<int, int|null>`
  - Lines 182-189: Add `@return Collection<int, SupplierQuote>` to `quotes()`
  - Lines 197-203: Add `@return Collection<int, RequestItem>` to `requestItems()`
  - Lines 211-228: Add `@return array<int, array<int, SupplierQuoteItem|null>>` to `priceMatrix()`
  - Lines 236-268: Add `@return array<int, int|null>` to `bestPricesByItem()`
  - Type all closure parameters in computed properties
- [ ] 4.2 Update `app/Livewire/QuotationEvaluationForm.php`
  - Line 99-114: Add `/** @var Collection<int, KeyAccount> */` before query
  - Lines 235-239: Add generic type annotation to `$quotes`
  - Line 242: Add generic type annotation to `$requestItems`
  - Lines 258-275: Type closure parameter in `first()` call
  - Document `buildSnapshotData()` return array shape

## 5. Update Relation Managers

- [ ] 5.1 Update `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php`
  - Line 84: Add explicit string cast with SafeCast
  - Line 192: Type closure in `mapWithKeys()`
  - Lines 251-262: Add null checks before property access
  - Lines 269-270: Replace `(float) ($get('cost_price') ?? 0)` with SafeCast
  - Lines 287-289: Replace unsafe cast with SafeCast
  - Line 378: Validate before `round()` call
  - Line 173: Document `$state` array structure
  - Lines 428-429: Type validate repeater items
  - Lines 664-687: Add array shape documentation to item construction
- [ ] 5.2 Update `app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php`
  - Line 82: Type closure return in supplier select
  - Lines 102-126: Add type hints to loop variables
  - Lines 145-146: Type closure in `mapWithKeys()`
- [ ] 5.3 Update `app/Filament/Resources/RequestResource/RelationManagers/SupplierOrdersRelationManager.php`
  - Apply same patterns as BuyerQuotesRelationManager
  - Type all closure parameters
  - Use SafeCast for numeric operations

## 6. Add PHPDoc to Item Models

- [ ] 6.1 Update `app/Models/BuyerQuoteItem.php`
  - Add `@return` types to attribute accessors
  - Document calculation methods
- [ ] 6.2 Update `app/Models/SupplierQuoteItem.php`
  - Add `@return` types to attribute accessors
  - Document relationship return types
- [ ] 6.3 Update `app/Models/BuyerOrderItem.php`
  - Line 196, 207: Add explicit return type documentation

## 7. Verification

- [ ] 7.1 Run PHPStan at level 8
  - Fix any new errors introduced
  - Document any baseline additions needed
- [ ] 7.2 Run PHPStan at level 9
  - Fix remaining type issues
  - Update CI to enforce level 9 for ERP code
- [ ] 7.3 Verify IDE autocomplete works
  - Test in PhpStorm/VS Code
  - Confirm Collection generics resolve correctly

## Summary

| Phase | Tasks | Files |
|-------|-------|-------|
| SafeCast Utility | 2 | 2 |
| Services | 3 | 3 |
| Models | 3 | 4 |
| Livewire Components | 2 | 2 |
| Relation Managers | 3 | 3 |
| Item Models | 3 | 3 |
| Verification | 3 | - |

**Total: 19 tasks, 17 files modified**

## Type Patterns Reference

### Collection Generic
```php
/** @var Collection<int, SupplierQuote> $quotes */
$quotes = $this->request->supplierQuotes()->get();
```

### Array Shape
```php
/** @return array{id: int, name: string, items: array<int, mixed>} */
```

### Typed Closure
```php
$items->filter(fn (BuyerQuoteItem $item): bool => $item->isVisible())
```

### Safe Cast
```php
$total = SafeCast::toFloat($get('line_total'));
```
