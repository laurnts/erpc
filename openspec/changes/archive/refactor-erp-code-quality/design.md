# Design: ERP Code Quality Refactor

## Context

The ERP trading system features (Quotation Evaluation, Profit & Loss, Key Accounts, Payment Terms) were implemented with functional correctness but deviated from established codebase patterns. This refactor brings the code into alignment with project conventions without changing user-facing behavior.

**Stakeholders:**
- Development team (code maintainability)
- QA team (test coverage)

**Constraints:**
- No breaking changes to existing data
- Backward compatibility with string values in database
- Must pass existing architecture tests

## Goals / Non-Goals

### Goals
- Establish type-safe enums for repeated string constants
- Eliminate code duplication across Resources and Livewire components
- Achieve 80%+ test coverage for new features
- Follow observer pattern for multi-tenancy auto-assignment
- Create reusable utilities for common operations

### Non-Goals
- Changing user-facing behavior
- Migrating existing data to new formats
- Adding new features
- Refactoring unrelated code

## Decisions

### Decision 1: Use Backed String Enums

**What:** Create PHP 8.1+ backed enums with string values matching existing database values.

**Why:**
- Maintains backward compatibility (existing `'percent'` values work)
- Provides IDE autocomplete and type checking
- Follows existing `BuyerQuoteStatus`, `SupplierQuoteStatus` patterns

**Example:**
```php
enum PrepaymentType: string implements HasLabel
{
    case PERCENT = 'percent';  // Matches existing DB value
    case FIXED = 'fixed';
}
```

**Alternatives considered:**
- Integer enums: Would require data migration
- Class constants: No type safety at parameter level

### Decision 2: Static Helper Class for Roman Numerals

**What:** Create `app/Support/RomanNumerals.php` with static methods.

**Why:**
- Simple utility with no state
- Used by multiple models (`QuotationEvaluation`, `ProfitAndLoss`)
- Static methods are appropriate for pure functions

**Alternatives considered:**
- Trait: Unnecessary coupling to models
- Service class: Over-engineering for stateless operation
- Carbon macro: Non-standard location for business logic

### Decision 3: Action Pattern for KeyAccount Creation

**What:** Create `app/Actions/KeyAccount/CreateKeyAccount.php` following existing Actions pattern.

**Why:**
- Single responsibility
- Reusable across 4+ locations
- Testable in isolation
- Follows `app/Actions/{Domain}/` convention

**Alternatives considered:**
- Service class: Overkill for single operation
- Model method: Couples creation logic to model
- Trait: Harder to test and override

### Decision 4: Observer Auto-Assignment Strategy

**What:** Create observers for `KeyAccount`, `QuotationEvaluation`, `ProfitAndLoss` that auto-assign `team_id` and `creator_id`.

**Why:**
- Matches existing pattern (`ArticleObserver`, `CompanyObserver`, etc.)
- Centralizes multi-tenancy logic
- Removes duplication from Resources/Livewire

**Implementation:**
```php
#[ObservedBy(KeyAccountObserver::class)]
final class KeyAccount extends Model
```

### Decision 5: Team-Scoped Unique Constraints

**What:** Change `profit_and_losses.pnl_number` from globally unique to team-scoped unique.

**Why:**
- Matches `quotation_evaluations` pattern
- Different teams should have independent number sequences
- Current global unique is incorrect for multi-tenant system

**Migration approach:**
1. Drop existing unique index
2. Add composite unique on `['team_id', 'pnl_number']`

## Risks / Trade-offs

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Enum cast breaks existing records | Low | High | Use string-backed enums matching existing values |
| Migration fails on duplicate PNL numbers | Low | Medium | Check for duplicates before migration |
| Test coverage takes significant time | Medium | Low | Prioritize critical paths first |

## Migration Plan

### Phase 1: Non-Breaking Additions
1. Create enums (additive)
2. Create utilities (additive)
3. Create observers (additive)
4. Create action (additive)

### Phase 2: Refactor Usages
1. Add enum casts to models
2. Update forms to use enums
3. Replace duplicated code with utilities/actions

### Phase 3: Database Fixes
1. Run migration for unique constraint fix
2. Add indexes

### Phase 4: Tests
1. Write feature tests
2. Write unit tests
3. Verify coverage meets 80%

### Rollback
All changes are additive or internal. Rollback by reverting commits. Database migration has `down()` method.

## Open Questions

1. **Unit enum extensibility:** Should `Unit` enum be configurable per team, or is a fixed set sufficient?
   - **Recommendation:** Start with fixed set; add custom units feature later if requested

2. **Document number format consistency:** Should QE and PNL use the same format pattern?
   - **Recommendation:** Keep existing formats for backward compatibility; document difference

3. **Relation manager splitting:** Should large relation managers be split into multiple files?
   - **Recommendation:** Use traits and shared components first; split only if still >500 lines

## Additional Decisions

### Decision 6: Reusable Form Schema Components

**What:** Create shared form components in `app/Filament/Forms/Components/`.

**Why:**
- Central Purchasing section is duplicated 5 times
- TaxCode select logic is duplicated 6+ times
- Changes need to be made in multiple places

**Implementation:**
```php
// CentralPurchasingSchema.php
final class CentralPurchasingSchema
{
    public static function make(): array
    {
        return [
            Select::make('prepared_by_id')
                ->relationship('preparedBy', 'name')
                ->createOptionForm(KeyAccountResource::getFormSchema())
                ->createOptionUsing(fn (array $data) => app(CreateKeyAccount::class)->execute($data)),
            // ... other fields
        ];
    }
}
```

### Decision 7: Item Calculation Trait

**What:** Extract `calculateItemTotals()` logic into a shared trait.

**Why:**
- Same calculation logic in 3 relation managers
- Subtle differences cause bugs when one is updated but not others
- Tax-inclusive/exclusive logic is complex and error-prone

**Implementation:**
```php
trait HasItemCalculations
{
    protected function calculateItemTotals(Set $set, Get $get, bool $includeMargin = false): void
    {
        $calculator = new ItemTotalCalculator(
            quantity: (float) ($get('quantity') ?? 0),
            unitPrice: (float) ($get('unit_price') ?? 0),
            taxRate: (float) ($get('tax_rate') ?? 0),
            isTaxInclusive: (bool) $get('is_tax_inclusive'),
            costPrice: $includeMargin ? (float) ($get('cost_price') ?? 0) : null,
        );

        $set('line_subtotal', $calculator->subtotal);
        $set('line_tax', $calculator->tax);
        $set('line_total', $calculator->total);

        if ($includeMargin) {
            $set('margin_amount', $calculator->marginAmount);
            $set('margin_percent', $calculator->marginPercent);
        }
    }
}
```

### Decision 8: PDF Generation Service Extension

**What:** Add QE and PNL PDF generation to existing `PdfGenerationService`.

**Why:**
- Existing service handles 4 document types
- QE and PNL PDF generation is inline in view pages
- Inconsistent with established pattern

**Alternatives considered:**
- Separate service for internal documents: Unnecessary complexity
- Keep inline: Violates DRY principle

### Decision 9: Currency Formatting Trait

**What:** Create `FormatsCurrency` trait for consistent currency formatting.

**Why:**
- `formatCurrency()` method duplicated in multiple places
- Inconsistent fallback handling
- Team base currency logic repeated

**Implementation:**
```php
trait FormatsCurrency
{
    protected function formatCurrency(float $value): string
    {
        $team = filament()->getTenant();
        $currency = $team?->getBaseCurrency();

        return $currency?->format($value) ?? number_format($value, 2);
    }
}
```

## File Locations

| Type | Location |
|------|----------|
| Enums | `app/Enums/{PrepaymentType,Unit,DeliveryType}.php` |
| Utilities | `app/Support/{RomanNumerals,ErpDefaults}.php` |
| Observers | `app/Observers/{KeyAccount,QuotationEvaluation,ProfitAndLoss}Observer.php` |
| Actions | `app/Actions/KeyAccount/CreateKeyAccount.php` |
| Form Components | `app/Filament/Forms/Components/{CentralPurchasingSchema,TaxCodeSelect}.php` |
| Traits | `app/Filament/Concerns/{HasItemCalculations,FormatsCurrency}.php` |
| Migration | `database/migrations/YYYY_MM_DD_fix_erp_schema_constraints.php` |
| Tests | `tests/{Feature,Unit}/...` |

## Dependency Graph

```
CreateKeyAccount (Action)
    └── used by: CentralPurchasingSchema, BuyersRelationManager, QuotationEvaluationForm

CentralPurchasingSchema (Form Component)
    ├── uses: CreateKeyAccount, KeyAccountResource::getFormSchema()
    └── used by: QE Resource, PNL Resource, BuyerQuotesRelationManager

HasItemCalculations (Trait)
    └── used by: BuyerQuotesRM, SupplierQuotesRM, SupplierOrdersRM

FormatsCurrency (Trait)
    └── used by: ViewRequest, SupplierQuoteComparison, RelationManagers

TaxCodeSelect (Form Component)
    └── used by: BuyerQuotesRM, SupplierQuotesRM, SupplierOrdersRM, ItemsRM

PdfGenerationService
    └── generates: BuyerQuote, BuyerOrder, BuyerInvoice, SupplierOrder, QE, PNL
```
