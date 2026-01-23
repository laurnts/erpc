# Implementation Tasks

## 1. Create Enums

- [ ] 1.1 Create `app/Enums/PrepaymentType.php` enum
  - Cases: `PERCENT`, `FIXED`
  - Implement `HasLabel`, `HasColor` contracts
  - Add `getSuffix()` and `getMaxValue()` helper methods
- [ ] 1.2 Create `app/Enums/Unit.php` enum
  - Cases: `PCS`, `KG`, `MT`, `SET`, `BOX`, `ROLL`, `PAIR`, `L`, `M`
  - Implement `HasLabel` contract
- [ ] 1.3 Create `app/Enums/DeliveryType.php` enum
  - Cases: `FOB`, `CIF`, `EXW`, `DDP`, `DAP`
  - Implement `HasLabel`, `HasDescription` contracts
- [ ] 1.4 Write unit tests for all new enums

## 2. Create Shared Utilities

- [ ] 2.1 Create `app/Support/RomanNumerals.php`
  - Static method `month(int $month): string`
  - Static method `number(int $number): string` (for extensibility)
- [ ] 2.2 Create `app/Support/ErpDefaults.php`
  - Constants: `QUOTE_VALIDITY_DAYS = 30`
  - Constants: `PAYMENT_TERMS_DAYS = 30`
  - Constants: `CURRENCY_CODE = 'USD'`
  - Constants: `DEFAULT_UNIT = 'pcs'`
- [ ] 2.3 Write unit tests for `RomanNumerals`
- [ ] 2.4 Refactor `QuotationEvaluation::generateQeNumber()` to use `RomanNumerals`
- [ ] 2.5 Refactor `ProfitAndLoss::generatePnlNumber()` to use `RomanNumerals`
- [ ] 2.6 Replace magic numbers with `ErpDefaults` constants across codebase

## 3. Create Observers

- [ ] 3.1 Create `app/Observers/KeyAccountObserver.php`
  - Auto-assign `team_id` from current tenant
  - Auto-assign `creator_id` from authenticated user
- [ ] 3.2 Create `app/Observers/QuotationEvaluationObserver.php`
  - Auto-assign `team_id` from current tenant
  - Auto-assign `creator_id` from authenticated user
- [ ] 3.3 Create `app/Observers/ProfitAndLossObserver.php`
  - Auto-assign `team_id` from current tenant
  - Auto-assign `creator_id` from authenticated user
- [ ] 3.4 Register observers in models using `#[ObservedBy]` attribute
- [ ] 3.5 Remove manual `team_id`/`creator_id` assignment from Resources/Livewire

## 4. Create Actions

- [ ] 4.1 Create `app/Actions/KeyAccount/CreateKeyAccount.php`
  - Accept DTO or array with `name`, `email`, `phone`, `is_active`
  - Return created `KeyAccount` model
- [ ] 4.2 Refactor `QuotationEvaluationForm::createKeyAccount()` to use action
- [ ] 4.3 Refactor `ProfitAndLossResource::createKeyAccount()` to use action
- [ ] 4.4 Refactor `BuyersRelationManager` inline creation to use action
- [ ] 4.5 Refactor `BuyerQuotesRelationManager` inline creation to use action

## 5. Add Model Scopes

- [ ] 5.1 Add `scopeActiveForCurrentTeam()` to `KeyAccount` model
- [ ] 5.2 Add `selectOptions()` method to `KeyAccount` model
- [ ] 5.3 Replace duplicated `getKeyAccountOptions()` calls with scope

## 6. Apply Enum Casts

- [ ] 6.1 Add `PrepaymentType` cast to `BuyerQuote` model
- [ ] 6.2 Add `Unit` cast to item models (`BuyerQuoteItem`, `SupplierQuoteItem`, etc.)
- [ ] 6.3 Add `DeliveryType` cast to `Company` model
- [ ] 6.4 Update Filament forms to use enum `->options()` instead of hardcoded arrays

## 7. Form Schema Consolidation

- [ ] 7.1 Create `app/Filament/Forms/Components/CentralPurchasingSchema.php`
  - Reusable schema with prepared_by, dept_head, deputy_director, approved_by
  - Support for inline Key Account creation
  - Used by: QE Resource, PNL Resource, BuyerQuotesRelationManager
- [ ] 7.2 Create `app/Filament/Forms/Components/TaxCodeSelect.php`
  - Encapsulate TaxCode query logic
  - Include default selection from team settings
  - Article default tax code support
- [ ] 7.3 Create `app/Filament/Concerns/HasItemCalculations.php` trait
  - `calculateItemTotals(Set $set, Get $get)` method
  - Tax-inclusive/exclusive calculation logic
  - Margin calculation logic
- [ ] 7.4 Create `app/Filament/Concerns/FormatsCurrency.php` trait
  - `formatCurrency(float $value): string` method
  - Uses team's base currency
  - Fallback to number_format
- [ ] 7.5 Update `BuyerQuotesRelationManager` to use new traits/components
- [ ] 7.6 Update `SupplierQuotesRelationManager` to use new traits/components
- [ ] 7.7 Update `SupplierOrdersRelationManager` to use new traits/components

## 8. PDF Generation Consolidation

- [ ] 8.1 Add `generateQuotationEvaluationPdf()` to `PdfGenerationService`
- [ ] 8.2 Add `generateProfitAndLossPdf()` to `PdfGenerationService`
- [ ] 8.3 Add `getQuotationEvaluationFilename()` to `PdfGenerationService`
- [ ] 8.4 Add `getProfitAndLossFilename()` to `PdfGenerationService`
- [ ] 8.5 Update `ViewQuotationEvaluation` to use `PdfGenerationService`
- [ ] 8.6 Update `ViewProfitAndLoss` to use `PdfGenerationService`
- [ ] 8.7 Extend `DownloadPdfAction` to support QE and PNL models

## 9. Relation Manager Refactoring

- [ ] 9.1 Extract item repeater schema to reusable method/component
  - Common fields: article_id, quantity, unit, unit_price, tax_code_id, etc.
- [ ] 9.2 Extract table column definitions to traits
  - Common columns: item description, quantity, total, margin
- [ ] 9.3 Extract `getBadge()` / `getBadgeColor()` patterns to trait
- [ ] 9.4 Reduce `BuyerQuotesRelationManager` from 1075 to ~600 lines
- [ ] 9.5 Reduce `SupplierOrdersRelationManager` from 995 to ~550 lines
- [ ] 9.6 Reduce `SupplierQuotesRelationManager` from 684 to ~400 lines

## 10. Database Fixes

- [ ] 10.1 Create migration to fix `profit_and_losses.pnl_number` unique constraint
  - Change from global unique to `unique(['team_id', 'pnl_number'])`
- [ ] 10.2 Add missing index on `profit_and_losses.pnl_date`
- [ ] 10.3 Add missing index on `quotation_evaluations.qe_date`
- [ ] 10.4 Add column comments to JSON `data` columns

## 11. Write Tests

- [ ] 11.1 Create `tests/Feature/KeyAccountTest.php`
  - Test CRUD operations
  - Test team scoping
  - Test policy authorization
- [ ] 11.2 Create `tests/Feature/QuotationEvaluationTest.php`
  - Test creation from request
  - Test QE number generation
  - Test data snapshot
- [ ] 11.3 Create `tests/Feature/ProfitAndLossTest.php`
  - Test creation from buyer quote
  - Test PNL number generation
  - Test status computation
- [ ] 11.4 Create `tests/Feature/BuyerQuotePaymentTermTest.php`
  - Test repeater functionality
  - Test cascade delete
- [ ] 11.5 Create `tests/Unit/Enums/PrepaymentTypeTest.php`
- [ ] 11.6 Create `tests/Unit/Enums/UnitTest.php`
- [ ] 11.7 Create `tests/Unit/Enums/DeliveryTypeTest.php`
- [ ] 11.8 Create `tests/Unit/Support/RomanNumeralsTest.php`

## 12. Documentation

- [ ] 12.1 Add enum documentation to decision-guide.md
- [ ] 12.2 Update model-creation skill with observer registration
- [ ] 12.3 Document new form components in ui-components skill
- [ ] 12.4 Archive this change after deployment

## Summary

| Phase | Tasks | Est. Lines Changed |
|-------|-------|-------------------|
| Enums | 4 | +300 |
| Utilities | 6 | +150 |
| Observers | 5 | +200 |
| Actions | 5 | +100, -200 |
| Model Scopes | 3 | +50, -100 |
| Enum Casts | 4 | +50, -30 |
| Form Consolidation | 7 | +400, -800 |
| PDF Consolidation | 7 | +150, -100 |
| Relation Manager Refactoring | 6 | +300, -900 |
| Database | 4 | +60 |
| Tests | 8 | +800 |
| Documentation | 4 | +100 |

**Total: 63 tasks**
**Net code reduction: ~530 lines (excluding tests)**
