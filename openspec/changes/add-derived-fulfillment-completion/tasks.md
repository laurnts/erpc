# Implementation Tasks

Awaiting proposal approval (three Open Questions in proposal.md). Do not start until approved.

## 1. Derived completion on Request

- [ ] 1.1 Add channel-completion accessors to `app/Models/Request.php`: goods (shipment quantity coverage per goods main item), services (acceptance-report coverage per services main item), vacuous-complete for empty channels; overall `isFulfilled`
- [ ] 1.2 Pest tests: mixed fulfilled, partial goods blocks, services-only vacuous case (extend `ItemLevelFulfillmentTest` patterns)

## 2. Stage gate

- [ ] 2.1 Locate the stage-transition validation and require derived fulfillment for the completed stage; error names incomplete channel(s)
- [ ] 2.2 Pest tests: blocked transition + message; allowed when fulfilled

## 3. Display

- [ ] 3.1 Request view page + list column/badge for per-channel and overall status
- [ ] 3.2 Feature test for visibility

## 4. QE ranking

- [ ] 4.1 Change `QuotationEvaluationForm` quote ordering (currently `->orderBy('total_base')`) to a goods-lines-only subtotal on mixed requests
- [ ] 4.2 Pest test: mixed-request ranking scenario from the spec delta; goods-only request unchanged

## 5. Verification

- [ ] 5.1 PHPStan clean on touched files (scope was just brought to zero — keep it)
- [ ] 5.2 `php artisan test --compact tests/Feature/Erp` green; pint clean
- [ ] 5.3 `openspec validate add-derived-fulfillment-completion --strict`
