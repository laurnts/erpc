# Implementation Tasks

## 1. Create Authorization Trait

- [ ] 1.1 Create `app/Livewire/Concerns/AuthorizesLivewireActions.php`
  - Add `authorizeAction(string $ability, mixed $model)` method
  - Add `ensureTeamOwnership(Model $model)` method
  - Add `belongsToCurrentTeam(Model $model)` method
  - Use `Illuminate\Support\Facades\Gate` for authorization
  - Use `Filament\Facades\Filament` for tenant access
- [ ] 1.2 Write unit tests for the trait
  - Test authorization success scenarios
  - Test authorization failure scenarios
  - Test team ownership validation

## 2. QuotationEvaluationForm Authorization

- [ ] 2.1 Add `AuthorizesLivewireActions` trait to component
- [ ] 2.2 Add team ownership check in `mount(Request $request)`
  - Call `$this->ensureTeamOwnership($request)`
  - Throw `AuthorizationException` if request doesn't belong to team
- [ ] 2.3 Add authorization check in `save()` method
  - Add `Gate::authorize('create', QuotationEvaluation::class)` at start
  - Wrap in try-catch for user-friendly error notification
- [ ] 2.4 Add authorization check in `createKeyAccount()` method
  - Add `Gate::authorize('create', KeyAccount::class)` at start
- [ ] 2.5 Add authorization check in `saveNewKeyAccount()` method
  - Add `Gate::authorize('create', KeyAccount::class)` at start
  - Show notification on permission denied
- [ ] 2.6 Write feature tests for QuotationEvaluationForm authorization
  - Test user with permission can create QE
  - Test user without permission gets denied
  - Test user cannot access request from another team
  - Test user with permission can create KeyAccount inline
  - Test user without KeyAccount permission gets denied

## 3. SupplierQuoteComparison Authorization

- [ ] 3.1 Add `AuthorizesLivewireActions` trait to component
- [ ] 3.2 Add team ownership check in `mount(Request $request)`
  - Call `$this->ensureTeamOwnership($request)`
- [ ] 3.3 Add authorization check in `applySelections()` method
  - Collect all quotes that will be modified
  - Call `Gate::authorize('update', $quote)` for each
  - Show notification on permission denied
- [ ] 3.4 Add validation in `selectSupplierForItem()` method
  - Verify `$supplierQuoteId` belongs to quotes for this request
  - Prevent selection of quotes from other requests/teams
- [ ] 3.5 Add validation in `selectSingleSupplier()` method
  - Verify `$supplierQuoteId` exists in `$this->quotes`
- [ ] 3.6 Write feature tests for SupplierQuoteComparison authorization
  - Test user with permission can apply selections
  - Test user without permission gets denied
  - Test user cannot access request from another team
  - Test user cannot select quote from another request
  - Test user cannot manipulate quote IDs

## 4. Error Handling

- [ ] 4.1 Create consistent error notification pattern
  - Use Filament `Notification::make()->danger()`
  - Standard message format for permission denied
- [ ] 4.2 Log authorization failures
  - Log user ID, attempted action, resource
  - Use `Log::warning()` for audit trail

## 5. Documentation

- [ ] 5.1 Document required permissions for each component
  - QuotationEvaluationForm requires: `view requests`, `create quotation evaluations`, `create key accounts`
  - SupplierQuoteComparison requires: `view requests`, `update supplier quotes`
- [ ] 5.2 Update component PHPDoc with authorization info

## Summary

| Phase | Tasks | Priority |
|-------|-------|----------|
| Create Trait | 2 | HIGH |
| QuotationEvaluationForm | 6 | HIGH |
| SupplierQuoteComparison | 6 | HIGH |
| Error Handling | 2 | MEDIUM |
| Documentation | 2 | LOW |

**Total: 18 tasks**

## Dependencies

```
AuthorizesLivewireActions (trait)
    └── used by: QuotationEvaluationForm, SupplierQuoteComparison

QuotationEvaluationForm
    ├── uses: QuotationEvaluationPolicy::create
    ├── uses: KeyAccountPolicy::create
    └── uses: RequestPolicy::view (implicit via team check)

SupplierQuoteComparison
    ├── uses: SupplierQuotePolicy::update
    └── uses: RequestPolicy::view (implicit via team check)
```
