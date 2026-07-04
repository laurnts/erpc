# Design: ERP Type Safety Improvements

## Context

The ERP module passes PHPStan at level 5 but has type safety gaps that would fail at level 9. These gaps reduce IDE support, allow potential runtime errors, and make the code harder to maintain.

**Stakeholders:**
- Development team (code quality, IDE support)
- QA team (static analysis coverage)

**Constraints:**
- No breaking changes to public APIs
- Must maintain backward compatibility
- PHPStan level 9 compliance target

## Goals / Non-Goals

### Goals
- Add PHPDoc generic annotations to all Collection returns
- Create safe casting utility for numeric operations
- Document array shapes for complex data structures
- Type all closure parameters in functional operations
- Enable PHPStan level 9 for ERP code

### Non-Goals
- Refactoring business logic
- Changing method signatures
- Adding runtime type checks (performance concern)
- Modifying database schema

## Decisions

### Decision 1: Create SafeCast Utility Class

**What:** Create `app/Support/SafeCast.php` with static methods for validated casting.

**Why:**
- Centralizes cast validation logic
- Makes intent explicit in code
- Enables consistent null handling
- Follows existing `app/Support/` pattern (RomanNumerals, ErpDefaults)

**Implementation:**
```php
final class SafeCast
{
    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }

    public static function toInt(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public static function toString(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        if (is_array($value) || is_object($value)) {
            return $default;
        }

        return (string) $value;
    }
}
```

**Alternatives considered:**
- Inline validation: Verbose, duplicated
- Laravel's `data_get()`: Doesn't validate types
- Custom trait: Less discoverable than utility class

### Decision 2: Use PHPDoc Array Shapes for Snapshot Data

**What:** Document complex array structures with `@param array{key: type}` syntax.

**Why:**
- PHPStan can validate array key access
- IDE autocomplete works on array keys
- Self-documenting code
- Catches typos in array key names

**Implementation:**
```php
/**
 * @return array{
 *     request: array{id: int, request_number: string, title: string},
 *     items: array<int, array{
 *         id: int,
 *         description: string,
 *         quantity: float,
 *         unit: string,
 *         prices: array<string, array{
 *             supplier_id: int,
 *             unit_price: float,
 *             line_subtotal: float,
 *             line_tax: float,
 *             line_total: float,
 *             is_best_price: bool,
 *             is_selected: bool
 *         }>
 *     }>,
 *     suppliers: array<int, array{
 *         id: int,
 *         name: string,
 *         currency_code: string,
 *         subtotal: float,
 *         tax_total: float,
 *         grand_total: float
 *     }>
 * }
 */
private function buildSnapshotData(): array
```

### Decision 3: Type Closure Parameters Explicitly

**What:** Add type hints to all closure parameters in `map()`, `filter()`, `mapWithKeys()`.

**Why:**
- PHPStan can validate closure body
- IDE autocomplete works on closure variables
- Catches incorrect property/method access
- Makes code self-documenting

**Before:**
```php
$quotes->filter(fn ($item) => ! $item->hide_from_pdf)
```

**After:**
```php
$quotes->filter(fn (SupplierQuoteItem $item): bool => ! $item->hide_from_pdf)
```

### Decision 4: Add Generic Types to Computed Properties

**What:** Add `@return Collection<int, Model>` to Livewire computed properties.

**Why:**
- Livewire `#[Computed]` properties lose type information
- IDE cannot infer types from query builder
- Loop variables need proper typing

**Implementation:**
```php
/**
 * Get all active supplier quotes for this request.
 *
 * @return Collection<int, SupplierQuote>
 */
#[Computed]
public function quotes(): Collection
{
    return $this->request->supplierQuotes()
        ->whereIn('status', [SupplierQuoteStatus::PENDING, SupplierQuoteStatus::SELECTED])
        ->with(['supplier', 'currency', 'items.requestItem'])
        ->get();
}
```

### Decision 5: Safe JSON Data Access Methods

**What:** Add typed getter methods for JSON `data` column access.

**Why:**
- `$this->data['items']` can throw undefined index
- No type guarantee on JSON decode
- Repeated validation logic

**Implementation:**
```php
/**
 * @return array<int, array{id: int, description: string, quantity: float, unit: string, prices: array<string, mixed>}>
 */
public function getItems(): array
{
    if (!is_array($this->data) || !isset($this->data['items'])) {
        return [];
    }

    return is_array($this->data['items']) ? $this->data['items'] : [];
}
```

## Risks / Trade-offs

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| PHPDoc bloat makes code verbose | Medium | Low | Keep docs close to usage, use IDE folding |
| SafeCast performance overhead | Low | Low | Micro-optimization, only called on user input |
| Array shape maintenance burden | Medium | Medium | Generate from TypeScript/JSON schema if needed |
| Over-documentation | Low | Low | Only document complex structures |

## Implementation Order

### Phase 1: Foundation
1. Create `SafeCast` utility class
2. Write unit tests for SafeCast

### Phase 2: Services
1. Update `PdfGenerationService` with SafeCast
2. Update `CreditLimitWarningService` with SafeCast
3. Add PHPDoc to service methods

### Phase 3: Models
1. Add array shape docs to `QuotationEvaluation`
2. Add array shape docs to `ProfitAndLoss`
3. Add safe data access methods

### Phase 4: Livewire Components
1. Add generic types to `SupplierQuoteComparison` computed properties
2. Add generic types to `QuotationEvaluationForm` query results
3. Type all closure parameters

### Phase 5: Relation Managers
1. Update `BuyerQuotesRelationManager` closures
2. Update `SupplierQuotesRelationManager` closures
3. Update `SupplierOrdersRelationManager` closures
4. Add array shape docs to repeater state

### Phase 6: Verification
1. Run PHPStan at level 9
2. Fix any remaining issues
3. Update CI configuration

## File Locations

| Type | Location |
|------|----------|
| Utility | `app/Support/SafeCast.php` |
| Tests | `tests/Unit/Support/SafeCastTest.php` |
| Modified Services | `app/Services/Erp/*.php` |
| Modified Models | `app/Models/QuotationEvaluation.php`, `app/Models/ProfitAndLoss.php` |
| Modified Livewire | `app/Livewire/*.php` |
| Modified Relation Managers | `app/Filament/Resources/RequestResource/RelationManagers/*.php` |
