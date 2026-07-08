# Implementation Tasks

Approved 2026-07-04 (decisions recorded in proposal.md).

## 1. Derived completion on Request

- [x] 1.1 Add channel-completion accessors to `app/Models/Request.php`: goods (shipment quantity coverage per goods main item), services (acceptance-report coverage per services main item), vacuous-complete for empty channels; overall `isFulfilled`
- [x] 1.2 Pest tests: mixed fulfilled, partial goods blocks, services-only vacuous case (extend `ItemLevelFulfillmentTest` patterns)

## 2. Stage gate

- [x] 2.1 Locate the stage-transition validation and require derived fulfillment for the completed stage; error names incomplete channel(s)
- [x] 2.2 Pest tests: blocked transition + message; allowed when fulfilled

## 3. Display

- [x] 3.1 Request view page + list column/badge for per-channel and overall status
- [x] 3.2 Feature test for visibility

## 4. QE ranking

- [x] 4.1 Change `QuotationEvaluationForm` quote ordering (currently `->orderBy('total_base')`) to a goods-lines-only subtotal on mixed requests, and display both goods subtotal and full total per supplier on mixed requests
- [x] 4.2 Pest test: mixed-request ranking scenario from the spec delta (ordering + both totals shown); goods-only request unchanged

## 5. Verification

- [x] 5.1 PHPStan clean on touched files (scope was just brought to zero — keep it)
- [x] 5.2 `php artisan test --compact tests/Feature/Erp` green; pint clean
- [x] 5.3 `openspec validate add-derived-fulfillment-completion --strict`
