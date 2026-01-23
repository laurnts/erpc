# Change: Refactor ERP Code Quality - Enums, Constants, Consolidation

## Why

Recent ERP feature additions (Quotation Evaluation, Profit & Loss, Key Accounts, Payment Terms) introduced code that deviates from codebase best practices:
- Hardcoded string values instead of enums
- Duplicated constants across models
- Missing observers for multi-tenancy auto-assignment
- No tests for new features
- Inconsistent patterns for repeated operations
- Massive relation managers (1000+ lines) with duplicated logic
- PDF generation not using centralized service
- Duplicated form schema sections

These issues create technical debt, reduce type safety, and make the codebase harder to maintain.

## What Changes

### Enums
- **ADDED** `PrepaymentType` enum for `percent`/`fixed` values
- **ADDED** `Unit` enum for measurement units (`pcs`, `kg`, `mt`, `set`, etc.)
- **ADDED** `DeliveryType` enum for supplier delivery classifications

### Shared Utilities
- **ADDED** `RomanNumerals` helper class (extracted from duplicated constants)
- **ADDED** `ErpDefaults` constants class for fallback values

### Observers
- **ADDED** `KeyAccountObserver` for auto-assigning `team_id`/`creator_id`
- **ADDED** `QuotationEvaluationObserver` for auto-assigning `team_id`/`creator_id`
- **ADDED** `ProfitAndLossObserver` for auto-assigning `team_id`/`creator_id`

### Actions
- **ADDED** `CreateKeyAccount` action to replace duplicated creation logic (4 locations)

### Form Schema Consolidation
- **ADDED** `CentralPurchasingSchema` reusable form section (5 duplications → 1)
- **ADDED** `ItemCalculationTrait` for line item totals (3 duplications → 1)
- **ADDED** `TaxCodeSelect` reusable component (6+ duplications → 1)
- **ADDED** `CurrencyFormatTrait` for consistent currency formatting

### PDF Generation Consolidation
- **MODIFIED** `PdfGenerationService` to include QE and PNL PDF generation
- **REMOVED** Inline PDF generation from `ViewQuotationEvaluation`
- **REMOVED** Inline PDF generation from `ViewProfitAndLoss`

### Relation Manager Refactoring
- **ADDED** `HasItemCalculations` trait for shared calculation logic
- **REFACTORED** `BuyerQuotesRelationManager` (1075 lines) - extract concerns
- **REFACTORED** `SupplierOrdersRelationManager` (995 lines) - extract concerns
- **REFACTORED** `SupplierQuotesRelationManager` (684 lines) - extract concerns

### Database
- **MODIFIED** `profit_and_losses` table unique constraint to be team-scoped
- **ADDED** Missing indexes for common query patterns

### Tests
- **ADDED** Feature tests for `KeyAccount` resource
- **ADDED** Feature tests for `QuotationEvaluation` resource
- **ADDED** Feature tests for `ProfitAndLoss` resource
- **ADDED** Unit tests for new enums
- **ADDED** Unit tests for `RomanNumerals` helper

## Impact

- Affected specs: `erp-quoting`, `erp-trading-core`
- Affected code:
  - `app/Enums/` - 3 new enum files
  - `app/Support/` - 2 new utility files
  - `app/Observers/` - 3 new observer files
  - `app/Actions/KeyAccount/` - 1 new action file
  - `app/Models/` - enum casts and trait updates
  - `app/Filament/Forms/` - new reusable form components
  - `app/Filament/Concerns/` - new traits for shared logic
  - `app/Filament/Resources/` - refactored relation managers
  - `app/Services/Erp/PdfGenerationService.php` - extended for QE/PNL
  - `database/migrations/` - 1 new migration for fixes
  - `tests/` - new test files

## Metrics

| Before | After | Reduction |
|--------|-------|-----------|
| `BuyerQuotesRelationManager`: 1075 lines | ~600 lines | ~44% |
| `SupplierOrdersRelationManager`: 995 lines | ~550 lines | ~45% |
| `calculateItemTotals`: 3 copies | 1 trait | 66% |
| Central Purchasing schema: 5 copies | 1 component | 80% |
| `createKeyAccount`: 4 copies | 1 action | 75% |
| TaxCode select logic: 6+ copies | 1 component | 83% |

## Breaking Changes

None. All changes are additive or internal refactoring. Existing string values (`'percent'`, `'pcs'`, etc.) are preserved as enum backing values.
