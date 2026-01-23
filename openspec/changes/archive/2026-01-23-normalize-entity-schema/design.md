# Design: Entity Schema Normalization

## Context

The ERP system has accumulated entity duplication through organic growth:
- `KeyAccount` was created for approval workflows but duplicates `People`
- Approval fields use mixed patterns (FK vs string)
- Contact information is denormalized as strings

This design addresses normalization while maintaining backward compatibility.

**Stakeholders:**
- Development team (schema maintainability)
- Data integrity (referential constraints)
- Reporting (queryable relationships)

**Constraints:**
- Zero downtime migration
- Backward compatibility for 2 releases
- Existing data must be preserved
- API responses should remain compatible

## Goals / Non-Goals

### Goals
- Single source of truth for people/contacts
- Referential integrity for all person references
- Queryable relationships (e.g., "documents approved by X")
- Type-safe role assignments
- Simplified data model

### Non-Goals
- Changing user-facing workflows
- Removing custom fields from People
- Combining Buyer/Supplier into single entity (already correct pattern)
- Adding new approval workflow features

## Decisions

### Decision 1: Extend People vs Create PersonBase

**What:** Add fields directly to `people` table rather than creating abstract base.

**Why:**
- Simpler migration path
- No need for polymorphic relationships
- Custom fields already work with People
- Maintains existing AI summary, notes features

**Alternatives considered:**
- Abstract `PersonBase` model: Over-engineering, breaks existing relationships
- Separate `contacts` table: Would create same duplication problem

### Decision 2: Central Purchasing Role-Based Filtering

**What:** Replace `is_key_account` boolean with `is_central_purchasing` boolean and `central_purchasing_role` enum for granular role-based filtering.

**Why:**
- More granular control over approval workflow roles
- Supports filtering by specific roles (KEY_ACCOUNT, DEPT_HEAD_SALES, DEPUTY_DIRECTOR, DIRECTOR)
- Enables conditional UI elements (e.g., Buyer tab only for KEY_ACCOUNT role)
- Better type safety with enum instead of boolean

**Implementation:**
```php
// CentralPurchasingRole enum
enum CentralPurchasingRole: string implements HasDescription, HasLabel
{
    case KEY_ACCOUNT = 'key_account';
    case DEPT_HEAD_SALES = 'dept_head_sales';
    case DEPUTY_DIRECTOR = 'deputy_director';
    case DIRECTOR = 'director';
}

// People model
public function scopeCentralPurchasing($query, ?CentralPurchasingRole $role = null)
{
    $query->where('is_central_purchasing', true);
    if ($role) {
        $query->where('central_purchasing_role', $role);
    }
    return $query;
}

// KeyAccountSelect component filters by role
KeyAccountSelect::makeWithRelationship(
    'prepared_by_id',
    'Prepared By',
    'preparedBy',
    CentralPurchasingRole::KEY_ACCOUNT, // Filter by specific role
    $buyerId
)
```

### Decision 3: Dual-Column Transition for Approval Fields

**What:** Add new FK columns alongside existing string columns, run dual-write during transition.

**Why:**
- Zero downtime migration
- Validation period before removing strings
- Rollback possible at any point

**Implementation:**
```php
// QuotationEvaluation model during transition
public function setApprovedByNameAttribute(?string $value): void
{
    $this->attributes['approved_by_name'] = $value;

    // Also set FK if we can match
    if ($value !== null) {
        $person = People::where('team_id', $this->team_id)
            ->where('name', $value)
            ->where('is_key_account', true)
            ->first();

        if ($person) {
            $this->attributes['approved_by_id'] = $person->id;
        }
    }
}
```

### Decision 4: ContactRole as Enum

**What:** Replace free-text `role` in `company_people` with `ContactRole` enum.

**Why:**
- Type safety
- Consistent values across system
- Filterable/queryable
- Supports future features (role-based notifications)

**Enum design:**
```php
enum ContactRole: string implements HasLabel
{
    case PRIMARY = 'primary';
    case BILLING = 'billing';
    case TECHNICAL = 'technical';
    case SALES = 'sales';
    case SUPPORT = 'support';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PRIMARY => 'Primary Contact',
            self::BILLING => 'Billing Contact',
            self::TECHNICAL => 'Technical Contact',
            self::SALES => 'Sales Contact',
            self::SUPPORT => 'Support Contact',
            self::OTHER => 'Other',
        };
    }
}
```

**Migration strategy:**
```sql
-- Map existing values
UPDATE company_people SET role = 'primary' WHERE role ILIKE '%primary%' OR role IS NULL;
UPDATE company_people SET role = 'billing' WHERE role ILIKE '%billing%' OR role ILIKE '%finance%';
UPDATE company_people SET role = 'technical' WHERE role ILIKE '%tech%' OR role ILIKE '%it%';
UPDATE company_people SET role = 'sales' WHERE role ILIKE '%sales%' OR role ILIKE '%account%';
UPDATE company_people SET role = 'other' WHERE role NOT IN ('primary', 'billing', 'technical', 'sales', 'support');
```

### Decision 5: Email Uniqueness Scope

**What:** Email uniqueness is scoped to team, not global.

**Why:**
- Multi-tenant isolation
- Same person can exist in multiple teams
- Allows team-specific contact management

**Implementation:**
```php
// Migration
$table->unique(['team_id', 'email'], 'people_team_email_unique');

// Model
public static function findByEmail(string $email, int $teamId): ?self
{
    return self::where('team_id', $teamId)
        ->where('email', $email)
        ->first();
}
```

## Data Migration Strategy

### Phase 1: KeyAccount → People

```php
// 1. Insert into people with mapping
DB::statement("
    INSERT INTO people (team_id, name, email, phone, is_key_account, is_active, creator_id, created_at, updated_at)
    SELECT team_id, name, email, phone, true, is_active, creator_id, created_at, updated_at
    FROM key_accounts
    RETURNING id, (SELECT id FROM key_accounts WHERE key_accounts.team_id = people.team_id AND key_accounts.name = people.name LIMIT 1) as old_id
");

// 2. Create mapping table for FK updates
CREATE TABLE key_account_people_map AS
SELECT ka.id as key_account_id, p.id as people_id
FROM key_accounts ka
JOIN people p ON p.team_id = ka.team_id AND p.name = ka.name AND p.is_key_account = true;

// 3. Update FKs
UPDATE quotation_evaluations qe
SET prepared_by_id = (SELECT people_id FROM key_account_people_map WHERE key_account_id = qe.prepared_by_id)
WHERE prepared_by_id IS NOT NULL;
```

### Phase 2: String Names → People

```php
// For each unique name in approval fields
$names = QuotationEvaluation::select('team_id', 'dept_head_sales_name')
    ->whereNotNull('dept_head_sales_name')
    ->distinct()
    ->get();

foreach ($names as $record) {
    // Find or create People record
    $person = People::firstOrCreate(
        ['team_id' => $record->team_id, 'name' => $record->dept_head_sales_name],
        ['is_key_account' => true, 'is_active' => true]
    );

    // Update FKs
    QuotationEvaluation::where('team_id', $record->team_id)
        ->where('dept_head_sales_name', $record->dept_head_sales_name)
        ->update(['dept_head_sales_id' => $person->id]);
}
```

## Risks / Trade-offs

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Data loss during migration | Low | High | Backup before migration, keep old columns |
| FK constraint failures | Medium | Medium | Migrate data before adding constraints |
| Performance regression | Low | Medium | Add indexes, test with production data volume |
| API breaking changes | Medium | Medium | Keep KeyAccount facade, version API |
| Name matching failures | Medium | Low | Create new People for unmatched, manual review |

## Open Questions

1. **Custom fields on People:** Should email/phone be schema columns or custom fields?
   - **Decision:** ✅ **IMPLEMENTED AS CUSTOM FIELDS** (via `PeopleField` enum)
   - **Rationale:** System already uses custom fields architecture for People. Email stored as tags (JSON array), phone/job_title as text fields. This maintains consistency with existing patterns.

2. **KeyAccount pivot table:** Keep `key_account_buyers` or rename to `people_buyers`?
   - **Decision:** Keep `key_account_buyers` (uses `key_account_id` FK which now points to `people.id`)
   - **Rationale:** FK already updated to point to `people.id`, no schema change needed. Filtering uses `is_central_purchasing` and `central_purchasing_role` in queries.

3. **Approval workflow history:** Should we track who was approver at time of approval?
   - **Decision:** Snapshot in JSON `data` column (existing pattern)
   - **Rationale:** Already implemented this way for QE/PNL

## File Locations

| Type | Location |
|------|----------|
| Enum | `app/Enums/ContactRole.php`, `app/Enums/CentralPurchasingRole.php` |
| Updated Models | `app/Models/{People,Company,QuotationEvaluation,ProfitAndLoss}.php` |
| Deprecated Model | `app/Models/KeyAccount.php` (removed) |
| Form Components | `app/Filament/Forms/Components/KeyAccountSelect.php`, `app/Filament/Forms/Components/ApprovalPersonnelSchema.php` |
| Migrations | `database/migrations/YYYY_MM_DD_*_normalize_*.php` |
| Tests | `tests/{Feature,Unit}/...` |

## Schema Comparison

### Before
```
key_accounts (id, team_id, name, email, phone, is_active)
people (id, team_id, name)  -- email/phone/job_title in custom fields
company_people (company_id, people_id, role VARCHAR)
quotation_evaluations (..., prepared_by_id → key_accounts, dept_head_sales_name VARCHAR, ...)
companies (..., contact_person VARCHAR)
```

### After (Current State) ✅
```
people (id, team_id, name, is_central_purchasing, central_purchasing_role ENUM)  -- email/phone/job_title remain as custom fields ✅
company_people (company_id, people_id, role ENUM)  -- ✅ COMPLETED (ContactRole enum)
quotation_evaluations (..., prepared_by_id → people, dept_head_sales_id → people, deputy_director_id → people, approved_by_id → people, ...)  -- ✅ COMPLETED
profit_and_losses (..., prepared_by_id → people, dept_head_sales_id → people, deputy_director_id → people, approved_by_id → people, ...)  -- ✅ COMPLETED
companies (..., contact_person VARCHAR)  -- ❌ NOT NEEDED (use company_people pivot with is_primary instead)
-- key_accounts table removed ✅
-- is_key_account deprecated (kept for backward compatibility, will be dropped) ✅
-- *_name columns deprecated (kept for backward compatibility) ✅
```

### After (Target State)
```
people (id, team_id, name, is_central_purchasing, central_purchasing_role ENUM)  -- email/phone/job_title as custom fields ✅
company_people (company_id, people_id, role ENUM)  -- ✅ COMPLETED (ContactRole enum)
quotation_evaluations (..., prepared_by_id → people, dept_head_sales_id → people, deputy_director_id → people, approved_by_id → people, ...)  -- ✅ COMPLETED
profit_and_losses (..., prepared_by_id → people, dept_head_sales_id → people, deputy_director_id → people, approved_by_id → people, ...)  -- ✅ COMPLETED
companies (..., contact_person VARCHAR)  -- ❌ NOT NEEDED (use company_people pivot with is_primary instead)
-- is_key_account column removed (future cleanup migration)
```
