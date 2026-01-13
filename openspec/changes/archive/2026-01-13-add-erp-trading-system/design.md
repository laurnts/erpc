# Design: Add ERP Trading System

## Context

### Background
The existing Relaticle CRM provides a solid foundation built on Laravel 12, Filament 4, and PostgreSQL with:
- Team-based multi-tenancy via Jetstream
- Custom fields system (relaticle/custom-fields package)
- Observer pattern for audit trails
- Soft deletes for data integrity
- AI summaries integration

However, it lacks the trading/brokerage workflow capabilities needed for:
- Request-to-payment lifecycle management
- Multi-supplier sourcing per transaction
- Multi-currency handling with exchange rates
- Quote → Order → Invoice → Payment document chain
- Margin calculation and profitability tracking

### Constraints
- Must preserve existing CRM functionality (non-breaking)
- Must follow established code conventions (strict types, final classes, etc.)
- Must maintain team-based isolation for all new entities
- Must achieve 80% test coverage
- Must support PostgreSQL (JSONB for flexible attributes)

### Stakeholders
- Trading intermediaries/brokers operating without inventory
- SMBs handling many one-time buyers with unpredictable product mixes
- Finance teams needing per-transaction profitability tracking

## Goals / Non-Goals

### Goals
1. Implement complete request-to-payment lifecycle
2. Support multi-supplier sourcing per request
3. Provide real-time margin calculation
4. Enable multi-currency operations with exchange rate tracking
5. Maintain full audit trail via activity logging
6. Enforce supplier confidentiality (buyer never sees supplier info)
7. Support quote validity extensions with reason tracking

### Non-Goals
1. Inventory management (this is for inventory-less brokers)
2. Third-party payment gateway integrations (manual journaling only)
3. Automated exchange rate fetching (manual entry)
4. Complex manufacturing/BOM workflows
5. Customer portal (admin-only for now)

## Decisions

### Decision 0: Reuse Existing Relaticle Infrastructure
**What:** Leverage existing packages and systems instead of creating duplicates.

**Reuse:**
| Component | Existing | Action |
|-----------|----------|--------|
| File attachments | Spatie Media Library (`media` table) | Use `HasMedia` trait, NOT new `attachments` table |
| Settings | Spatie Settings package | Create `ErpSettings` class, NOT new `settings` table |
| Custom Fields | `relaticle/custom-fields` package | Add `UsesCustomFields` trait to ERP entities |
| Tasks/Notes | `taskables`/`noteables` polymorphic | Add ERP entities to morphMap |
| RBAC | None exists | Install `spatie/laravel-permission` (recommended) |

**Why:**
- Avoids duplicating battle-tested functionality
- Maintains consistency with existing CRM patterns
- Reduces code to maintain
- Preserves existing CRM functionality

### Decision 1: Request as Atomic Unit
**What:** Each Request represents a complete buyer inquiry lifecycle from initial request through final payment.

**Why:**
- Enables instant per-transaction profitability calculation
- Provides clear accountability per transaction
- Simplifies document chain traceability (quote → order → invoice → payment)

**Alternatives Considered:**
- Using existing Opportunity model: Rejected because Opportunity lacks the document chain structure
- Project as atomic unit: Rejected because Projects are better suited as optional grouping containers

### Decision 2: Separate Buyers/Suppliers WITH Optional Company Link
**What:** Create dedicated `buyers` and `suppliers` tables with **optional** `company_id` FK to existing `companies` table.

**Schema:**
```php
Schema::create('buyers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

    // OPTIONAL link to CRM company (for contact sync)
    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

    $table->string('code', 20);  // BUY-001
    // ... ERP-specific fields (credit_limit, is_on_hold, etc.)
});
```

**Why this approach:**
- ERP works completely standalone (no CRM dependency)
- Existing CRM remains untouched (zero breaking changes)
- If company exists in CRM, can link to access People (contacts)
- Gradual migration path if needed

**Benefits:**
- Buyer-specific fields (credit_limit) don't pollute Companies
- Supplier-specific fields (lead_time) don't pollute Companies
- Different validation rules per entity type
- Can have same business as both Buyer AND Supplier (different records)

**Alternatives Rejected:**
- Extending companies table: Would break existing CRM forms/UI
- Using polymorphism: Complex queries, performance concerns
- Single parties table: Different attributes and relationships per type

### Decision 3: Flat Tags Instead of Hierarchical Categories
**What:** Use flat `tags` table shared between Articles and Suppliers, displayed as "Categories" in UI.

**Why:**
- Maximum flexibility for unpredictable product mix
- Enables finding suppliers by what they supply (same tags)
- Prevents duplicate categorization maintenance
- Simpler queries and better performance

**Alternatives Considered:**
- Hierarchical categories: Rejected due to complexity and reduced flexibility
- Separate category tables per entity: Rejected due to maintenance overhead

### Decision 4: Single Base Currency for Sales
**What:** All buyer-facing amounts (quotes, orders, invoices) use a single base currency. Supplier amounts stored in original currency with exchange rate snapshots.

**Why:**
- Simplifies buyer-facing documents
- Enables accurate margin calculation in consistent currency
- Maintains audit trail of actual supplier costs

**Alternatives Considered:**
- Full multi-currency on buyer side: Rejected due to complexity in reporting
- Convert supplier amounts immediately: Rejected because it loses original cost data

### Decision 5: Value Locking on Orders
**What:** Prices and terms are editable on quotes but locked when converted to orders.

**Why:**
- Quotes are negotiation documents that can change
- Orders are contractual documents that must be immutable
- Ensures accounting integrity

**Implementation:**
- Use Eloquent observers to prevent updates to locked fields
- Store snapshot values at conversion time
- Amendments require new documents (credit notes, revised POs)

### Decision 6: Manual Journaling with Proof Uploads
**What:** No third-party payment integrations. All payments recorded manually with required proof uploads.

**Why:**
- Target users often deal with bank transfers, LCs, and cash
- Third-party integrations add complexity and compliance requirements
- Proof uploads provide audit trail

**Alternatives Considered:**
- Stripe/PayPal integration: Rejected because target market primarily uses bank transfers
- Optional integrations: Deferred to future enhancement

### Decision 7: Request Items with Match Workflow
**What:** Request items capture vague buyer requests first (description), then get matched to actual articles during sourcing phase.

**Why:**
- Buyers often request vaguely ("tyre for Prius 2020, good brand")
- Sales team needs to clarify and match to specific articles
- Maintains traceability from original request to fulfilled article

**Implementation:**
- `description` field captures original request (always required)
- `article_id` nullable until matched
- `matched_at` and `matched_by` for audit trail
- Validation prevents moving to supplier quoting until all items matched

### Decision 8: Item-Level Tax Handling ⭐ NEW
**What:** Tax is handled at the line item level with a tax code dropdown and inc/exc tax checkbox.

**Why (ERP Best Practice):**
- Different items may have different tax rates (some exempt, some standard)
- Some suppliers provide prices inclusive of tax, others exclusive
- Accurate tax calculation requires per-item granularity
- Audit trail requires snapshot of tax rate at time of entry

**Implementation:**
```php
// tax_codes table (dropdown options)
- code: 'PPN11', 'PPN0', 'EXEMPT', 'NOTAX'
- name: 'PPN 11%', 'PPN 0%', 'Tax Exempt', 'No Tax'
- rate: 11.00, 0.00, 0.00, 0.00

// Line item fields
- tax_code_id: FK to tax_codes (dropdown)
- is_tax_inclusive: boolean (checkbox: "Price includes tax")
- tax_rate: decimal (snapshot of rate at entry)
- unit_price: user-entered price
- unit_price_exc_tax: calculated net price
- tax_amount: calculated tax for this line
- subtotal: qty × unit_price_exc_tax
- total: subtotal + tax_amount
```

**Calculation Logic:**
```php
if ($is_tax_inclusive) {
    $unit_price_exc_tax = $unit_price / (1 + $tax_rate / 100);
} else {
    $unit_price_exc_tax = $unit_price;
}
```

**Alternatives Considered:**
- Header-level tax only: Rejected because items often have different tax treatments
- Fixed tax rate: Rejected because different products have different tax codes

### Decision 9: Outbound Shipment Support ⭐ NEW
**What:** Shipments support both inbound (from supplier) and outbound (to buyer) tracking.

**Why:**
- Complete fulfillment cycle requires tracking delivery to end customer
- Discrepancy detection needed for both incoming and outgoing goods

**Implementation:**
```php
// shipments table
- supplier_order_id: nullable FK (for inbound)
- buyer_order_id: nullable FK (for outbound)
- type: 'inbound' | 'outbound'

// shipment_items table
- supplier_order_item_id: nullable FK (for inbound)
- buyer_order_item_id: nullable FK (for outbound)
```

### Decision 10: Invoice Items (Line-Level) ⭐ NEW
**What:** Invoices have line items that mirror the quote/order item structure.

**Why:**
- Enables partial invoicing (invoice 50 of 100 units now, rest later)
- Maintains document chain traceability: quote item → order item → invoice item
- Supports item-level tax verification
- Invoice should look like quote (consistent document structure)

**Implementation:**
```php
// buyer_invoice_items
- buyer_invoice_id: FK
- buyer_order_item_id: nullable FK (traceability)
- article_id: nullable FK
- description, quantity, unit
- tax_code_id, is_tax_inclusive, tax_rate (snapshot)
- unit_price, unit_price_exc_tax, subtotal, tax_amount, total
- sort_order

// supplier_invoice_items - same structure
```

**Alternatives Considered:**
- Header-only invoices: Rejected because it breaks traceability and prevents partial invoicing

### Decision 11: Credit Notes as Invoice Type ⭐ NEW
**What:** Credit notes are invoices with `type='credit_note'` and negative amounts, rather than separate tables.

**Why:**
- Simpler data model (no separate credit_notes table)
- Credit notes follow same workflow as invoices
- Easy to link credit note to original invoice via `original_invoice_id`
- Reporting treats them consistently

**Implementation:**
```php
// InvoiceType enum
enum InvoiceType: string {
    case PREPAYMENT = 'prepayment';
    case BALANCE = 'balance';
    case STANDARD = 'standard';
    case CREDIT_NOTE = 'credit_note';
    case DEBIT_NOTE = 'debit_note';
}

// buyer_invoices / supplier_invoices
- type: InvoiceType (includes credit_note, debit_note)
- original_invoice_id: nullable FK (for credit notes, references original)
- credit_reason: text (required for credit notes)
- amount: decimal (negative for credit notes)
```

**Alternatives Considered:**
- Separate credit_notes table: Rejected because it duplicates structure and complicates reporting

### Decision 12: Header-Level Tax Default ⭐ NEW
**What:** Quotes and orders have `default_tax_code_id` for convenience when adding items.

**Why:**
- Reduces repetitive selection when all items have same tax treatment
- Items can still override the default
- Follows hierarchy: article default → document default → team default

**Implementation:**
```php
// Quote/Order headers
$table->foreignId('default_tax_code_id')->nullable()->constrained('tax_codes');

// When adding item, tax_code defaults from:
// 1. article.default_tax_code_id (if article selected)
// 2. quote/order.default_tax_code_id
// 3. team's default tax code (is_default = true)
```

## Data Model Overview

```
                     ┌──────────┐
                     │   Tags   │
                     └────┬─────┘
              ┌──────────┴──────────┐
              ▼                     ▼
        ┌──────────┐          ┌──────────┐
        │ Articles │          │Suppliers │
        └────┬─────┘          └────┬─────┘
             │                     │
             └──────────┬──────────┘
                        ▼
     ┌────────┐    ┌─────────┐
     │ Buyers │───>│Requests │<───┌─────────┐
     └────────┘    └────┬────┘    │Projects │
                        │         │(optional)│
              ┌─────────┼─────────┘
              ▼         ▼
    ┌─────────────┐  ┌────────────┐
    │Supplier     │  │Buyer       │
    │Quotes (N)   │  │Quotes (ver)│
    └──────┬──────┘  └─────┬──────┘
           │               │
           ▼               ▼
    ┌─────────────┐  ┌────────────┐
    │Supplier     │  │Buyer       │
    │Orders (N)   │  │Order (1)   │
    └──────┬──────┘  └─────┬──────┘
           │               │
           ▼               ▼
    ┌─────────────┐  ┌────────────┐
    │Supplier     │  │Buyer       │
    │Invoices (N) │  │Invoices    │
    └──────┬──────┘  └─────┬──────┘
           │               │
           ▼               ▼
    ┌─────────────┐  ┌────────────┐
    │Supplier     │  │Buyer       │
    │Payments     │  │Payments    │
    └─────────────┘  └────────────┘
```

## Migration Order

Database migrations must be created in dependency order:

```
Phase 1:
1. currencies
2. exchange_rates (depends on users)
3. tags
4. roles
5. permissions
6. role_permissions
7. user_roles
8. settings
9. buyers
10. suppliers
11. articles
12. taggables (depends on tags, articles, suppliers)
13. supplier_articles (depends on suppliers, articles)

Phase 2:
14. projects (must be before requests)
15. requests (depends on buyers, projects)
16. request_items (depends on requests, articles)

Phase 3:
17. supplier_quotes (depends on requests, suppliers)
18. supplier_quote_items (depends on supplier_quotes, request_items, articles)
19. buyer_quotes (depends on requests)
20. buyer_quote_items (depends on buyer_quotes, request_items, articles, supplier_quote_items)
21. buyer_quote_extensions (depends on buyer_quotes)

Phase 4:
22. buyer_orders (depends on requests, buyer_quotes)
23. buyer_order_items (depends on buyer_orders, articles, suppliers)
24. supplier_orders (depends on requests, suppliers, supplier_quotes)
25. supplier_order_items (depends on supplier_orders, articles)

Phase 5:
26. shipments (depends on requests, supplier_orders)
27. shipment_items (depends on shipments, supplier_order_items)
28. buyer_invoices (depends on requests, buyer_orders)
29. buyer_payments (depends on buyer_invoices)
30. supplier_invoices (depends on requests, supplier_orders, suppliers)
31. supplier_payments (depends on supplier_invoices)
32. attachments (polymorphic)
33. request_activities (depends on requests)
```

## Risks / Trade-offs

### Risk 1: Complexity of Multi-Supplier Flow
**Risk:** Multi-supplier per request increases UI and logic complexity.
**Mitigation:**
- Clear UI with separate supplier cards
- Consolidated cost summary with exchange rate display
- Well-documented stage transitions

### Risk 2: Exchange Rate Accuracy
**Risk:** Manual exchange rate entry may be delayed or incorrect.
**Mitigation:**
- Warn users when rates are older than 1 day
- Allow inline rate updates on quotes/orders
- Store rate snapshot with each transaction

### Risk 3: Performance with Large Data Sets
**Risk:** Complex queries across multiple tables may slow down.
**Mitigation:**
- Strategic database indexes (see schema)
- Eager loading in Eloquent relationships
- Consider caching for computed values (margin, totals)

### Risk 4: Testing Complexity
**Risk:** Complex workflows require extensive testing.
**Mitigation:**
- Phase-by-phase implementation with tests per phase
- Factory patterns for complex relationships
- Integration tests for full workflow scenarios

## Open Questions

1. **CRM-ERP Entity Linking:** Should existing Companies be optionally linkable to Buyers/Suppliers? (Deferred to future enhancement)

2. **Notification System:** Should we add email notifications for quote expiration, payment overdue, etc.? (Deferred to future enhancement)

3. **API Access:** Should we expose REST API for external integrations? (Deferred to future enhancement)

4. **Reporting Module:** Should we add dedicated reporting with charts and exports? (Deferred to future enhancement)

## File Structure

```
app/
├── Enums/
│   ├── RequestStage.php
│   ├── SupplierQuoteStatus.php
│   ├── BuyerQuoteStatus.php
│   ├── OrderStatus.php
│   ├── InvoiceStatus.php
│   ├── InvoiceType.php
│   ├── PaymentMethod.php
│   ├── ShipmentType.php
│   ├── ShipmentStatus.php
│   ├── ItemCondition.php
│   └── AttachmentType.php
├── Models/
│   ├── Tag.php
│   ├── Currency.php
│   ├── ExchangeRate.php
│   ├── Buyer.php
│   ├── Supplier.php
│   ├── Article.php
│   ├── Project.php
│   ├── Request.php
│   ├── RequestItem.php
│   ├── SupplierQuote.php
│   ├── SupplierQuoteItem.php
│   ├── BuyerQuote.php
│   ├── BuyerQuoteItem.php
│   ├── BuyerQuoteExtension.php
│   ├── BuyerOrder.php
│   ├── BuyerOrderItem.php
│   ├── SupplierOrder.php
│   ├── SupplierOrderItem.php
│   ├── Shipment.php
│   ├── ShipmentItem.php
│   ├── BuyerInvoice.php
│   ├── BuyerPayment.php
│   ├── SupplierInvoice.php
│   ├── SupplierPayment.php
│   ├── Attachment.php
│   ├── RequestActivity.php
│   ├── Role.php
│   ├── Permission.php
│   └── Setting.php
│   └── Concerns/
│       ├── HasTags.php
│       ├── HasAttachments.php
│       └── HasRoles.php
├── Observers/
│   ├── TagObserver.php
│   ├── BuyerObserver.php
│   ├── SupplierObserver.php
│   ├── ArticleObserver.php
│   ├── ProjectObserver.php
│   ├── RequestObserver.php
│   ├── SupplierQuoteObserver.php
│   ├── BuyerQuoteObserver.php
│   ├── BuyerOrderObserver.php
│   ├── SupplierOrderObserver.php
│   ├── ShipmentObserver.php
│   ├── BuyerInvoiceObserver.php
│   └── SupplierInvoiceObserver.php
├── Policies/
│   ├── BuyerPolicy.php
│   ├── SupplierPolicy.php
│   ├── ArticlePolicy.php
│   ├── ProjectPolicy.php
│   ├── RequestPolicy.php
│   └── ...
├── Services/
│   └── CurrencyService.php
└── Filament/
    └── Resources/
        ├── TagResource.php
        ├── CurrencyResource.php
        ├── ExchangeRateResource.php
        ├── BuyerResource.php
        ├── SupplierResource.php
        ├── ArticleResource.php
        ├── ProjectResource.php
        ├── RequestResource.php
        └── ...
```

## Conventions to Follow

Based on existing codebase patterns:

1. **Models:** Use `HasTeam`, `HasCreator` traits for team-scoped entities
2. **Observers:** Use `final readonly class` pattern
3. **Foreign Keys:** `team_id` with `cascadeOnDelete()`, `creator_id` with `nullOnDelete()`
4. **Soft Deletes:** All business entities use `SoftDeletes`
5. **Strict Types:** `declare(strict_types=1)` in all PHP files
6. **Final Classes:** All classes are `final` by default
7. **Return Types:** All methods have explicit return types
8. **PHPDoc:** Use for relationship generics
9. **Testing:** Pest 4 with 80% coverage minimum
