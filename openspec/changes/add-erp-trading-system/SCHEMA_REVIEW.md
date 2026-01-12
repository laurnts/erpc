# Schema Review: Reuse vs New

A careful analysis of what to reuse from Relaticle CRM vs what must be new for ERP.

---

## Executive Summary

| Decision | Count | Details |
|----------|-------|---------|
| **REUSE existing** | 4 | Media Library, Settings, Tasks/Notes polymorphic, Custom Fields |
| **CREATE NEW with linking** | 2 | Buyers, Suppliers (link to Companies optionally) |
| **CREATE NEW standalone** | 25 | Core ERP tables that don't exist |
| **DO NOT CREATE** | 3 | attachments, settings, custom RBAC (use existing/packages) |

**Net new tables: ~27** (down from 35 in original schema)

---

## Part 1: What MUST Be Reused (Do Not Duplicate)

### 1.1 Spatie Media Library (Already Installed)

**Existing:** `media` table with full attachment support

**DO NOT CREATE:** `attachments` table from schema-v2.md

**Instead:**
```php
// Add HasMedia trait to ERP models
final class BuyerPayment extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('payment_proof')
            ->singleFile();
    }
}

// Usage
$payment->addMedia($file)->toMediaCollection('payment_proof');
```

**Collections to define:**
- `payment_proof` - on BuyerPayment, SupplierPayment
- `shipping_doc` - on Shipment
- `pod` - on Shipment (proof of delivery)
- `quote_doc` - on SupplierQuote, BuyerQuote
- `invoice_copy` - on SupplierInvoice

---

### 1.2 Spatie Settings (Already Installed)

**Existing:** Spatie Laravel Settings package

**DO NOT CREATE:** `settings` table from schema-v2.md

**Instead:**
```php
// Create a Settings class
final class ErpSettings extends Settings
{
    public string $default_currency = 'USD';
    public float $default_tax_percent = 11.0;
    public int $quote_validity_days = 14;
    public string $company_name = '';
    public string $invoice_prefix = 'INV';
    public string $po_prefix = 'PO';

    public static function group(): string
    {
        return 'erp';
    }
}
```

---

### 1.3 Custom Fields System (Already Installed)

**Existing:** `relaticle/custom-fields` package with team-scoped polymorphic custom fields

**REUSE:** Add `UsesCustomFields` trait to ERP models

**Entities that should support custom fields:**
- Buyer
- Supplier
- Article
- Request

```php
final class Buyer extends Model implements HasCustomFields
{
    use UsesCustomFields;
    // ...
}
```

---

### 1.4 Tasks & Notes Polymorphic System

**Existing:**
- `tasks` table + `taskables` pivot
- `notes` table + `noteables` pivot

**REUSE:** Add ERP entities to polymorphic relationships

```php
// In Request model
public function tasks(): MorphToMany
{
    return $this->morphToMany(Task::class, 'taskable');
}

public function notes(): MorphToMany
{
    return $this->morphToMany(Note::class, 'noteable');
}
```

**Entities that should support tasks/notes:**
- Request
- Buyer
- Supplier

---

## Part 2: Critical Decision - Buyers/Suppliers vs Companies

### Current State

**`companies` table has:**
```
id, team_id, creator_id, account_owner_id, name,
timestamps, soft_deletes
```

**`buyers` needs:**
```
id, team_id, creator_id, code, name, contact_name, email, phone, address,
credit_limit, is_on_hold, default_currency, notes,
timestamps, soft_deletes
```

**`suppliers` needs:**
```
id, team_id, creator_id, code, name, contact_name, email, phone, address,
default_payment_terms, default_lead_time_days, default_currency, notes,
timestamps, soft_deletes
```

### Analysis

| Approach | Pros | Cons |
|----------|------|------|
| **A) Extend companies** | Single source of truth | Pollutes CRM, different validation rules, breaking change |
| **B) Separate tables** | Clean separation, ERP-specific attributes | Duplication if same entity in both |
| **C) Separate + optional link** | Best of both, no breaking changes | Slightly more complex queries |

### **DECISION: Option C - Separate tables with optional `company_id` FK**

```php
Schema::create('buyers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

    // Optional link to CRM company (for contact sync)
    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

    $table->string('code', 20)->unique();
    $table->string('name', 255);
    // ... ERP-specific fields
});
```

**Benefits:**
1. ERP works completely standalone
2. Existing CRM untouched (no breaking changes)
3. If company exists, can link for contact (People) access
4. Can gradually migrate/link data

---

## Part 3: Requests vs Opportunities

### Current State

**`opportunities` table:**
```
id, team_id, creator_id, company_id, contact_id, name, order_column,
timestamps, soft_deletes
```

**`requests` needs:**
```
id, team_id, creator_id, project_id, buyer_id, request_number, title, stage,
requirements, base_currency, closed_at,
timestamps, soft_deletes
```

### Analysis

These are **fundamentally different entities**:

| Aspect | Opportunity | Request |
|--------|-------------|---------|
| Purpose | Sales pipeline tracking | Full transaction lifecycle |
| Complexity | Simple (name only) | Complex (items, quotes, orders, invoices) |
| Workflow | Kanban board | Multi-stage document flow |
| Children | None | Items, Quotes, Orders, Invoices, Payments |

### **DECISION: Create separate `requests` table**

Cannot extend opportunities - completely different data model.

Optional future enhancement: Add `opportunity_id` FK to requests for CRM-ERP linking.

---

## Part 4: RBAC Decision

### Options

1. **Spatie Permission package** (not installed)
2. **Custom RBAC tables** (as in schema-v2.md)
3. **Filament Shield** (Filament-native RBAC)

### Analysis

| Option | Pros | Cons |
|--------|------|------|
| Spatie Permission | Battle-tested, middleware, Blade directives | Another dependency |
| Custom tables | Full control, no dependency | Must build from scratch |
| Filament Shield | Filament-native, auto-generates policies | Tightly coupled to Filament |

### **DECISION: Use Spatie Permission package**

Reasons:
1. Already using 5 Spatie packages (consistent ecosystem)
2. Well-documented, battle-tested
3. Great Filament integration
4. Provides more than we'd build custom

**DO NOT CREATE:** `roles`, `permissions`, `role_permissions`, `user_roles` tables

**Instead:** Install `spatie/laravel-permission` and use its migrations.

---

## Part 5: Final Table List

### Tables to CREATE NEW (27 total)

**Foundation (4):**
```
1. tags
2. taggables (polymorphic)
3. currencies
4. exchange_rates
```

**Master Data (3):**
```
5. buyers (with optional company_id)
6. suppliers (with optional company_id)
7. articles
8. supplier_articles (pivot)
```

**Request Core (3):**
```
9. projects
10. requests
11. request_items
```

**Supplier Side (6):**
```
12. supplier_quotes
13. supplier_quote_items
14. supplier_orders
15. supplier_order_items
16. supplier_invoices
17. supplier_payments
```

**Buyer Side (7):**
```
18. buyer_quotes
19. buyer_quote_items
20. buyer_quote_extensions
21. buyer_orders
22. buyer_order_items
23. buyer_invoices
24. buyer_payments
```

**Shipments (2):**
```
25. shipments
26. shipment_items
```

**Activity (1):**
```
27. request_activities
```

### Tables to NOT CREATE (reuse existing)

| Proposed | Use Instead |
|----------|-------------|
| `attachments` | Spatie Media Library (`media` table) |
| `settings` | Spatie Settings (class-based) |
| `roles` | Spatie Permission |
| `permissions` | Spatie Permission |
| `role_permissions` | Spatie Permission |
| `user_roles` | Spatie Permission |

---

## Part 6: Team Scoping Pattern

All new ERP tables MUST follow the existing Relaticle pattern:

```php
// Standard FK pattern
$table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
$table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

// Standard traits
final class Buyer extends Model implements HasCustomFields
{
    use HasTeam;      // Auto team_id scoping
    use HasCreator;   // Auto creator_id assignment
    use SoftDeletes;
    use UsesCustomFields;
}
```

---

## Part 7: Integration Points with CRM

### Tasks & Notes Integration

ERP entities can have tasks/notes attached via existing polymorphic system:

```php
// Add to morphMap in AppServiceProvider
Relation::morphMap([
    // ... existing
    'request' => \App\Models\Request::class,
    'buyer' => \App\Models\Buyer::class,
    'supplier' => \App\Models\Supplier::class,
]);
```

### AI Summary Integration

Requests could use `HasAiSummary` trait for AI-generated summaries:

```php
final class Request extends Model
{
    use HasAiSummary;
}
```

### Media Integration

ERP entities use same media system as CRM:

```php
$payment->addMedia($proofFile)->toMediaCollection('payment_proof');
$shipment->addMedia($podFile)->toMediaCollection('pod');
```

---

## Part 8: Migration Dependencies

```
Layer 0 (no deps):
  currencies
  tags

Layer 1 (users only):
  exchange_rates

Layer 2 (teams, users):
  buyers
  suppliers
  articles
  projects

Layer 3 (above + buyers):
  requests

Layer 4 (above + requests):
  request_items
  supplier_quotes
  buyer_quotes

Layer 5 (above + quotes):
  supplier_quote_items
  buyer_quote_items
  buyer_quote_extensions

Layer 6 (above + buyer_quotes):
  supplier_orders
  buyer_orders

Layer 7 (above + orders):
  supplier_order_items
  buyer_order_items

Layer 8 (above + supplier_orders):
  shipments
  supplier_invoices

Layer 9 (above):
  shipment_items
  supplier_payments
  buyer_invoices

Layer 10:
  buyer_payments
  request_activities

Always last:
  taggables
  supplier_articles
```

---

## Part 9: Risks & Mitigations

### Risk 1: Spatie Permission Conflicts
**Risk:** Permission names might conflict with Filament defaults.
**Mitigation:** Use namespaced permissions (e.g., `erp.requests.create`).

### Risk 2: Media Collections Collision
**Risk:** Media collection names might conflict.
**Mitigation:** Prefix with entity name (e.g., `buyer_payment_proof`).

### Risk 3: Custom Fields Entity Types
**Risk:** Entity type strings might conflict.
**Mitigation:** Use full class names or namespaced identifiers.

### Risk 4: Team Scope Leaks
**Risk:** Forgot to add team scoping to a model.
**Mitigation:** Architecture test to verify all ERP models have `HasTeam`.

---

## Part 10: Recommendations

1. **Install Spatie Permission** before starting ERP development
2. **Create comprehensive architecture tests** for new models
3. **Use database transactions** for complex multi-table operations
4. **Add indexes** per schema-v2.md specifications
5. **Follow exact FK patterns** from existing Relaticle models
6. **Test team isolation** thoroughly for each new entity

---

## Approval Checklist

Before implementing, confirm:

- [ ] Schema review approved
- [ ] Spatie Permission installation approved
- [ ] Buyer/Supplier linking approach approved
- [ ] Media collection naming approved
- [ ] Migration order validated
- [ ] Architecture tests planned
