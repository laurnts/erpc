# Bugfix Implementation — 2026-06-23

## 1. Module Overview

- **Document Version:** 1.0 (Laravel 12 / Filament 5)
- **Core Purpose:** Record production bug fixes discovered via `storage/logs/laravel.log` on 2026-06-23, including root cause, code changes, and verification steps.
- **Main Fixes:**
  * Fix 1 — `PNLStatus` enum autoload failure on Linux (PSR-4 filename mismatch)
  * Fix 2 — Duplicate `request_number` on create when a soft-deleted request holds the same number

---

## 2. Technical Structure

### Dependencies

- Laravel Framework ^12.0
- Filament ^5.0
- PostgreSQL (unique constraints include soft-deleted rows)
- PHP ^8.4 with PSR-4 autoloading via Composer

### Configuration

- No config changes required
- Team ERP settings (`request_number_prefix`, default `REQ`) unchanged
- Database constraint: `requests_team_id_request_number_unique` on `(team_id, request_number)`

### Core Files

| File | Purpose |
|------|---------|
| `app/Enums/PNLStatus.php` | PNL status enum (renamed from `PnlStatus.php`) |
| `app/Observers/RequestItemObserver.php` | Resets approved PNL when request items are added |
| `app/Observers/RequestObserver.php` | Auto-generates `request_number` on create |
| `app/Observers/ProjectObserver.php` | Auto-generates `project_number` on create (same pattern) |
| `tests/Feature/Erp/RequestTest.php` | Regression test for soft-delete number sequencing |

### Core Methods

```php
// app/Observers/RequestObserver.php
private function generateRequestNumber(Request $request): string

// app/Observers/ProjectObserver.php
private function generateProjectNumber(Project $project): string

// app/Observers/RequestItemObserver.php
public function created(RequestItem $requestItem): void
```

---

## 3. Implementation Details

### Fix 1 — `Class "App\Enums\PNLStatus" not found`

#### Symptoms

```
production.ERROR: Class "App\Enums\PNLStatus" not found
at app/Observers/RequestItemObserver.php:26
```

Triggered when adding a request item via Filament (`ItemsRelationManager` → `RequestItem::create()` → `RequestItemObserver::created()`).

#### Root Cause

The enum class was named `PNLStatus` but the file was `app/Enums/PnlStatus.php`. Composer PSR-4 maps `App\Enums\PNLStatus` → `app/Enums/PNLStatus.php`. On Linux (case-sensitive filesystem in production at `/var/www/html/`), autoloading failed.

Other enums in the project follow exact case matching (e.g. `QEStatus.php` → `enum QEStatus`).

#### Change

| Action | Path |
|--------|------|
| Rename | `app/Enums/PnlStatus.php` → `app/Enums/PNLStatus.php` |

No PHP code changes. All existing imports `use App\Enums\PNLStatus;` remain valid.

#### Affected References

- `app/Observers/RequestItemObserver.php`
- `app/Observers/ProfitAndLossObserver.php`
- `app/Models/ProfitAndLoss.php`
- `app/Models/BuyerQuote.php`
- `app/Filament/Resources/RequestResource/RelationManagers/Concerns/HasRequestStageTab.php`
- `app/Filament/Resources/RequestResource/Pages/ViewRequest.php`

#### Deployment Steps

```bash
git mv app/Enums/PnlStatus.php app/Enums/PNLStatus.php
composer dump-autoload -o
php artisan optimize:clear
```

On case-insensitive filesystems (macOS), use `git mv` to ensure Git records the case change.

#### Verification

```bash
php -r "require 'vendor/autoload.php'; echo App\Enums\PNLStatus::APPROVED->value;"
# Expected: approved

php artisan tinker --execute="echo App\Enums\PNLStatus::NEED_APPROVAL->getLabel();"
# Expected: Not Approved yet
```

---

### Fix 2 — Duplicate `request_number` unique constraint violation

#### Symptoms

```
SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint
"requests_team_id_request_number_unique"
DETAIL: Key (team_id, request_number)=(1, REQ-2026-0024) already exists.
```

Triggered when creating a request via Filament (`CreateRequest` → `RequestObserver::creating()`).

#### Root Cause

| Layer | Behavior |
|-------|----------|
| PostgreSQL unique index | Applies to **all** rows, including soft-deleted |
| `RequestObserver::generateRequestNumber()` (before fix) | Used `Request::query()` which **excludes** soft-deleted rows |

Example from production:

- Request id `24`, number `REQ-2026-0024`, soft-deleted at `2026-06-23 04:40:34`
- New create at `04:41:32` saw `REQ-2026-0023` as latest active record
- Generated `REQ-2026-0024` again → unique constraint violation

Other observers already handle this correctly (`SupplierOrderObserver`, `ShipmentObserver`, `SupplierQuoteObserver`, `ArticleObserver` use `withTrashed()`).

#### Change

**`app/Observers/RequestObserver.php`** — include trashed records when finding the highest sequence:

```php
$lastRequest = Request::query()
    ->withTrashed()
    ->where('team_id', $request->team_id)
    ->where('request_number', 'like', $pattern)
    ->orderByDesc('request_number')
    ->first();
```

**`app/Observers/ProjectObserver.php`** — same preventive fix for `project_number` generation (Project also uses `SoftDeletes`).

**`tests/Feature/Erp/RequestTest.php`** — regression test:

```php
it('increments request number past soft-deleted records', function (): void {
    // Creates REQ-{year}-0024, soft-deletes it, then asserts next is 0025
});
```

#### Business Rules

- Request numbers are auto-generated in `{prefix}-{YYYY}-{NNNN}` format when the field is empty
- Numbers are **not reused** after soft-delete; sequence continues past trashed records
- Unique constraint is per `(team_id, request_number)`

#### Deployment Steps

```bash
php artisan optimize:clear
```

No migration required. Code-only change.

#### Verification

```bash
# Confirm trashed record exists and next number skips it
php artisan tinker --execute="
\$last = App\Models\Request::withTrashed()
    ->where('team_id', 1)
    ->where('request_number', 'like', 'REQ-2026-%')
    ->orderByDesc('request_number')
    ->first();
echo \$last->request_number;
"
# Expected: REQ-2026-0024 (or current highest)

# Run regression test (dev environment with Pest)
./vendor/bin/pest tests/Feature/Erp/RequestTest.php --filter=\"increments request number past soft-deleted\"
```

Manual UI check: create a new request in Filament after a soft-deleted request held the previous highest number. The new request should receive the next sequential number (e.g. `REQ-2026-0025`).

---

## 4. Events / Integration Points

### Fix 1 — Observer chain

```
RequestItem::create()
  → eloquent.created
  → RequestItemObserver::created()
  → ProfitAndLoss query using PNLStatus::APPROVED
  → resets PNL to PNLStatus::NEED_APPROVAL
```

### Fix 2 — Observer chain

```
Request::save() (create)
  → eloquent.creating
  → RequestObserver::creating()
  → generateRequestNumber() if request_number empty
  → insert into requests
```

---

## 5. Files Changed Summary

| File | Change Type | Fix |
|------|-------------|-----|
| `app/Enums/PnlStatus.php` → `app/Enums/PNLStatus.php` | Rename | Fix 1 |
| `app/Observers/RequestObserver.php` | Modified | Fix 2 |
| `app/Observers/ProjectObserver.php` | Modified | Fix 2 (preventive) |
| `tests/Feature/Erp/RequestTest.php` | Modified | Fix 2 test |

---

## 6. Post-Deploy Checklist

- [ ] `composer dump-autoload -o` run on production after enum rename
- [ ] `php artisan optimize:clear` run after deploy
- [ ] Add request item on an existing request — no `PNLStatus` class error
- [ ] Create new request after soft-deleting the highest-numbered request — no unique violation
- [ ] Confirm `storage/logs/laravel.log` shows no new errors for these flows

---

## 7. Related Patterns (Reference)

When implementing auto-incrementing document numbers on soft-deletable models:

1. Always query with `withTrashed()` when reading the last used number
2. DB unique constraints do not respect Laravel soft deletes
3. Follow existing patterns in `SupplierOrderObserver`, `ShipmentObserver`, `SupplierQuoteObserver`

When naming PHP enum files:

1. Filename must match class name exactly for PSR-4 (case-sensitive on Linux)
2. Use `git mv` for case-only renames on case-insensitive dev machines
