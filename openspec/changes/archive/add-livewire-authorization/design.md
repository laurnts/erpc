# Design: Livewire Component Authorization

## Context

The ERP module has two Livewire components that perform database mutations without authorization checks:

1. **QuotationEvaluationForm** (353 lines)
   - `save()` creates `QuotationEvaluation` records
   - `createKeyAccount()` creates `KeyAccount` records
   - No `$this->authorize()` calls anywhere

2. **SupplierQuoteComparison** (408 lines)
   - `applySelections()` updates `SupplierQuoteItem` records via raw queries
   - `selectSupplierForItem()`, `selectSingleSupplier()` modify state without validation
   - Direct `SupplierQuoteItem::query()->update()` bypasses model events

**Stakeholders:**
- Security team (authorization enforcement)
- ERP users (affected by permission changes)
- Development team (implementation)

**Constraints:**
- Must use existing policies (no new permission definitions)
- Must maintain existing functionality for authorized users
- Must follow established Livewire patterns in codebase

## Goals / Non-Goals

### Goals
- Add authorization checks to all data-modifying Livewire methods
- Validate team ownership before any database mutation
- Create reusable trait for Livewire authorization patterns
- Add comprehensive test coverage for authorization scenarios
- Provide clear error messages for permission denials

### Non-Goals
- Creating new policies or permissions
- Refactoring components beyond authorization
- Adding validation rules (separate concern)
- Changing component UI or behavior

## Decisions

### Decision 1: Use Laravel Gate for Authorization

**What:** Use `Gate::authorize()` in Livewire components instead of custom checks.

**Why:**
- Leverages existing policy infrastructure
- Consistent with Filament resource authorization
- Provides automatic exception handling
- Integrates with Laravel's authorization events

**Implementation:**
```php
use Illuminate\Support\Facades\Gate;

public function save(): void
{
    Gate::authorize('create', QuotationEvaluation::class);

    // ... existing save logic
}
```

**Alternatives considered:**
- Manual policy checks: More verbose, inconsistent
- Middleware: Doesn't work for individual methods
- Custom trait with manual checks: Reinventing the wheel

### Decision 2: Create AuthorizesLivewireActions Trait

**What:** Create `app/Livewire/Concerns/AuthorizesLivewireActions.php` trait.

**Why:**
- Centralizes authorization patterns
- Provides helper methods for common checks
- Follows existing `app/Livewire/Concerns/` convention
- Makes authorization intent explicit

**Implementation:**
```php
trait AuthorizesLivewireActions
{
    protected function authorizeAction(string $ability, mixed $model = null): void
    {
        Gate::authorize($ability, $model ?? static::class);
    }

    protected function ensureTeamOwnership(Model $model): void
    {
        if (!$this->belongsToCurrentTeam($model)) {
            throw new AuthorizationException('This record does not belong to your team.');
        }
    }

    protected function belongsToCurrentTeam(Model $model): bool
    {
        $team = Filament::getTenant();
        return $model->team_id === $team?->getKey();
    }
}
```

### Decision 3: Validate Request Ownership in QuotationEvaluationForm

**What:** Verify the Request belongs to the current team before creating QE.

**Why:**
- Request is passed via `mount()` from URL parameters
- Malicious user could manipulate request ID
- Must verify team ownership before any operation

**Implementation:**
```php
public function mount(Request $request): void
{
    $this->ensureTeamOwnership($request);
    $this->request = $request;
    // ...
}
```

### Decision 4: Check Each Quote Before Update in SupplierQuoteComparison

**What:** Authorize update on each SupplierQuote before modifying its items.

**Why:**
- `applySelections()` modifies multiple quotes
- Each quote may have different authorization requirements
- Batch updates should not bypass per-record authorization

**Implementation:**
```php
public function applySelections(): void
{
    // Authorize update on each affected quote
    foreach ($this->quotes as $quote) {
        if (array_key_exists($quote->getKey(), $quoteSelections)) {
            Gate::authorize('update', $quote);
        }
    }

    // ... existing update logic
}
```

### Decision 5: Show User-Friendly Authorization Errors

**What:** Catch `AuthorizationException` and show Filament notification.

**Why:**
- Default exception handling shows ugly error page
- Livewire components should handle errors gracefully
- Users need actionable feedback

**Implementation:**
```php
public function save(): void
{
    try {
        Gate::authorize('create', QuotationEvaluation::class);
    } catch (AuthorizationException $e) {
        Notification::make()
            ->title('Permission Denied')
            ->body('You do not have permission to create quotation evaluations.')
            ->danger()
            ->send();
        return;
    }

    // ... existing save logic
}
```

**Alternative:** Use `#[Authorize]` attribute (not available in Livewire 3)

## Risks / Trade-offs

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Existing users lose access | Medium | High | Document required permissions, audit existing user roles |
| Performance impact from multiple checks | Low | Low | Gate caches policy instances |
| Breaking existing workflows | Low | Medium | Test thoroughly with existing permissions |
| Notification spam on repeated failures | Low | Low | Rate-limit error notifications |

## Implementation Order

### Phase 1: Create Foundation
1. Create `AuthorizesLivewireActions` trait
2. Add trait to `BaseLivewireComponent` (optional) or individual components

### Phase 2: QuotationEvaluationForm
1. Add team ownership check in `mount()`
2. Add authorization check in `save()`
3. Add authorization check in `createKeyAccount()`
4. Add authorization check in `saveNewKeyAccount()`
5. Write tests

### Phase 3: SupplierQuoteComparison
1. Add team ownership check in `mount()`
2. Add authorization check in `applySelections()`
3. Add validation in `selectSupplierForItem()`
4. Add validation in `selectSingleSupplier()`
5. Write tests

### Rollback
Revert trait addition and remove authorization calls. No database changes required.

## File Locations

| Type | Location |
|------|----------|
| Trait | `app/Livewire/Concerns/AuthorizesLivewireActions.php` |
| Modified Components | `app/Livewire/QuotationEvaluationForm.php`, `app/Livewire/SupplierQuoteComparison.php` |
| Tests | `tests/Feature/Livewire/QuotationEvaluationFormTest.php`, `tests/Feature/Livewire/SupplierQuoteComparisonTest.php` |

## Authorization Matrix

| Component | Method | Policy | Ability |
|-----------|--------|--------|---------|
| QuotationEvaluationForm | `mount()` | RequestPolicy | view |
| QuotationEvaluationForm | `save()` | QuotationEvaluationPolicy | create |
| QuotationEvaluationForm | `createKeyAccount()` | KeyAccountPolicy | create |
| QuotationEvaluationForm | `saveNewKeyAccount()` | KeyAccountPolicy | create |
| SupplierQuoteComparison | `mount()` | RequestPolicy | view |
| SupplierQuoteComparison | `applySelections()` | SupplierQuotePolicy | update (per quote) |
| SupplierQuoteComparison | `selectSupplierForItem()` | - | Team ownership check |
| SupplierQuoteComparison | `selectSingleSupplier()` | - | Team ownership check |
