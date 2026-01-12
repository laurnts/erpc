# Trading System Database Schema v2
## Laravel Migration Reference

> **Aligned with:** uiux-v2.md wireframes
> **Platform:** Laravel 12 + Filament 4 + PostgreSQL
> **Version:** 2.0 - Multi-supplier, categories (tags), single base currency
> **Terminology:** Request = atomic unit; Project = optional grouping of Requests

---

## Schema Overview

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              ENTITY RELATIONSHIP DIAGRAM                             │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                      │
│                           ┌──────────┐                                              │
│                           │CATEGORIES│ (tags table)                                 │
│                           └──────────┘                                              │
│                                │                                                     │
│                    ┌───────────┴───────────┐                                        │
│                    ▼                       ▼                                        │
│               ┌─────────┐            ┌──────────┐                                   │
│               │ARTICLES │            │SUPPLIERS │                                   │
│               └─────────┘            └──────────┘                                   │
│                    │                      │                                          │
│                    └──────────┬───────────┘                                         │
│                               ▼                                                      │
│  ┌─────────┐  ┌─────────┐  ┌─────────────┐                                          │
│  │ BUYERS  │─>│PROJECTS │─>│  REQUESTS   │<── 1 Request : N Suppliers               │
│  └─────────┘  │(optional│  └─────────────┘                                          │
│               │grouping)│       │                                                    │
│               └─────────┘       │                                                    │
│                             │                                                       │
│                     ┌───────┴────────┬─────────────┐                                │
│                     ▼                ▼             ▼                                │
│              ┌─────────────┐  ┌────────────┐ ┌──────────┐                           │
│              │  SUPPLIER   │  │   BUYER    │ │ SHIPMENTS│                           │
│              │   QUOTES    │  │   QUOTES   │ │(multiple)│                           │
│              │ (multiple)  │  │(versioned) │ └──────────┘                           │
│              └─────────────┘  └────────────┘                                        │
│                     │                │                                               │
│                     ▼                ▼                                               │
│              ┌─────────────┐  ┌────────────┐                                        │
│              │  SUPPLIER   │  │   BUYER    │ (1 consolidated)                       │
│              │   ORDERS    │  │   ORDER    │                                        │
│              │ (multiple)  │  └────────────┘                                        │
│              └─────────────┘        │                                               │
│                     │               │                                                │
│                     ▼               ▼                                                │
│              ┌─────────────┐  ┌────────────┐                                        │
│              │  SUPPLIER   │  │   BUYER    │                                        │
│              │  INVOICES   │  │  INVOICES  │                                        │
│              │ (multiple)  │  └────────────┘                                        │
│              └─────────────┘        │                                               │
│                     │               │                                                │
│                     ▼               ▼                                                │
│              ┌─────────────┐  ┌────────────┐                                        │
│              │  SUPPLIER   │  │   BUYER    │                                        │
│              │  PAYMENTS   │  │  PAYMENTS  │                                        │
│              └─────────────┘  └────────────┘                                        │
│                                                                                      │
│  ┌────────────────┐    ┌─────────────────┐    ┌──────────────┐                      │
│  │ EXCHANGE_RATES │    │   ATTACHMENTS   │    │    ROLES     │                      │
│  │  (per date)    │    │  (polymorphic)  │    │ (permissions)│                      │
│  └────────────────┘    └─────────────────┘    └──────────────┘                      │
│                                                                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Categories System (Internal: tags)

UI displays "Categories" but the internal table name remains `tags` for simplicity.

### 1.1 tags

Flat categories shared between articles and suppliers.

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->string('slug', 100)->unique();          // URL-friendly
    $table->string('color', 7)->nullable();         // Hex color for UI badge
    $table->timestamps();
    
    $table->index('slug');
});
```

**UI Mapping:** Category selector (autocomplete, prevents duplicates)

---

### 1.2 taggables (Polymorphic Pivot)

Links tags to articles and suppliers.

```php
Schema::create('taggables', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->morphs('taggable');                     // taggable_type, taggable_id
    $table->timestamps();
    
    $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
    $table->index(['taggable_type', 'taggable_id']);
});
```

**Usage:**
```php
// Article with categories (relationship still called 'tags' internally)
$article->tags()->attach($tagId);
$article->tags; // Returns Tag collection

// Supplier with categories
$supplier->tags()->attach($tagId);

// Find suppliers by category
Supplier::whereHas('tags', fn($q) => $q->where('slug', 'chemicals'))->get();
```

---

## 2. Currency & Exchange Rates

### 2.1 currencies

Supported currencies.

```php
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->char('code', 3)->unique();              // USD, IDR, EUR
    $table->string('name', 100);                    // US Dollar, Indonesian Rupiah
    $table->string('symbol', 10);                   // $, Rp, €
    $table->integer('decimal_places')->default(2); // 2 for USD, 0 for IDR
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Seed with:**
```php
['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'decimal_places' => 0],
['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2],
['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2],
```

---

### 2.2 exchange_rates

Historical exchange rates for conversion tracking.

```php
Schema::create('exchange_rates', function (Blueprint $table) {
    $table->id();
    $table->char('base_currency', 3);               // From currency (e.g., IDR)
    $table->char('target_currency', 3);             // To currency (e.g., USD)
    $table->decimal('rate', 20, 10);                // Exchange rate
    $table->date('effective_date');                 // Date this rate applies
    $table->string('source', 100)->nullable();      // Where rate came from (manual, bank, etc.)
    $table->foreignId('recorded_by')->nullable()->constrained('users');
    $table->timestamps();
    
    $table->unique(['base_currency', 'target_currency', 'effective_date']);
    $table->index('effective_date');
});
```

**Usage:**
```php
// Get rate for a specific date
ExchangeRate::where('base_currency', 'IDR')
    ->where('target_currency', 'USD')
    ->where('effective_date', '<=', $date)
    ->orderBy('effective_date', 'desc')
    ->first();
```

**UI Mapping:** Settings → Exchange Rates, inline on quotes/orders

---

## 3. Master Data Tables

### 3.1 buyers

```php
Schema::create('buyers', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();           // BUY-001
    $table->string('name', 255);
    $table->string('contact_name', 255)->nullable();
    $table->string('email', 255)->nullable();
    $table->string('phone', 50)->nullable();
    $table->text('address')->nullable();
    $table->decimal('credit_limit', 15, 2)->nullable();
    $table->boolean('is_on_hold')->default(false);
    $table->char('default_currency', 3)->default('USD');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('code');
    $table->index('is_on_hold');
});
```

---

### 3.2 suppliers

```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();           // SUP-001
    $table->string('name', 255);
    $table->string('contact_name', 255)->nullable();
    $table->string('email', 255)->nullable();
    $table->string('phone', 50)->nullable();
    $table->text('address')->nullable();
    $table->string('default_payment_terms', 100)->nullable();
    $table->integer('default_lead_time_days')->nullable();
    $table->char('default_currency', 3)->default('USD');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('code');
});
```

**Relation:** `$supplier->tags()` via taggables

---

### 3.3 articles

```php
Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('name', 255);
    $table->string('sku', 50)->nullable()->unique(); // Optional internal SKU
    $table->text('description')->nullable();
    $table->string('unit', 50);                     // pcs, kg, ltr, box
    $table->jsonb('attributes')->nullable();        // Flexible specs
    $table->timestamps();
    $table->softDeletes();

    $table->index(['attributes'], 'articles_attributes_gin')->algorithm('gin');
});
```

**Relation:** `$article->tags()` via taggables (replaces category_id)

---

### 3.4 supplier_articles (Pivot)

```php
Schema::create('supplier_articles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
    $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
    $table->decimal('last_quoted_price', 15, 2)->nullable();
    $table->char('last_quoted_currency', 3)->nullable();
    $table->date('last_quoted_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->unique(['supplier_id', 'article_id']);
});
```

---

## 4. Request & Project Core

### 4.1 requests

The atomic unit representing a single buyer inquiry from initial request through final payment.

```php
Schema::create('requests', function (Blueprint $table) {
    $table->id();
    $table->string('request_number', 20)->unique(); // REQ-2024-0001
    $table->foreignId('project_id')->nullable()     // Optional grouping
          ->constrained('requests')->nullOnDelete();
    $table->foreignId('buyer_id')->constrained();
    $table->string('title', 255);
    $table->string('stage', 50)->default('new');
    $table->text('requirements')->nullable();

    // Single base currency for all sales and reporting
    // Suppliers can quote in any currency; amounts auto-convert to base
    $table->char('base_currency', 3)->default('USD');

    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index('buyer_id');
    $table->index('project_id');
    $table->index('stage');
    $table->index('request_number');
    $table->index('created_at');
});
```

NOTE: Removed `buyer_currency`. All buyer-facing documents use `base_currency`.
Supplier quotes/orders/invoices store their original currency + exchange rate.

---

### 4.2 projects

Groups multiple related Requests for large deals. Optional container.

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->string('project_number', 20)->unique(); // PRJ-2024-0001
    $table->foreignId('buyer_id')->constrained();
    $table->string('name', 255);
    $table->text('description')->nullable();
    $table->string('status', 50)->default('active'); // active, completed, on_hold, cancelled
    $table->timestamps();
    $table->softDeletes();

    $table->index('buyer_id');
    $table->index('status');
});
```

NOTE: Projects table must be created BEFORE requests table due to foreign key constraint.

**Stage Values:**
```php
const STAGES = [
    'new',
    'sourcing',
    'supplier_quoting',
    'buyer_quoting',
    'negotiation',
    'buyer_po_received',
    'supplier_po_issued',
    'fulfillment',
    'invoicing',
    'closed',
    'cancelled',
];
```

---

### 4.3 request_items

Captures what the buyer asked for (even if vague). Articles are linked later when clarified during sourcing.

```php
Schema::create('request_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained()->cascadeOnDelete();

    // Always captured (even if vague)
    $table->string('description', 500);           // "Tyre for Toyota Prius 2020"
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);                   // pcs, kg, set

    // Linked when clarified (nullable until matched)
    $table->foreignId('article_id')->nullable()->constrained('articles');
    $table->timestamp('matched_at')->nullable();  // When article was linked
    $table->foreignId('matched_by')->nullable()->constrained('users'); // Who matched it

    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();                        // Preserve history if item removed

    $table->index('request_id');
    $table->index('article_id');
});
```

**Workflow:**
1. **NEW stage:** Create items with description only (`article_id = NULL`)
2. **SOURCING stage:** Research, clarify with buyer, match to actual articles
3. **SUPPLIER_QUOTING:** All items must have `article_id` matched

**Validation Rule:** Cannot move to `SUPPLIER_QUOTING` until all items have `article_id` set.

| Indicator | Meaning |
|-----------|---------|
| `article_id = NULL` | Not yet clarified |
| `article_id = 123` | Clarified & matched |
| `matched_at` | When clarification happened |
| `matched_by` | Who performed the match |

---

## 5. Supplier Quotes (Multiple per Project)

### 5.1 supplier_quotes

```php
Schema::create('supplier_quotes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained()->cascadeOnDelete();
    $table->foreignId('supplier_id')->constrained();
    $table->string('quote_number', 50)->nullable();
    $table->string('status', 30)->default('pending'); // pending, selected, rejected, expired
    
    // Amounts in supplier's currency
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->char('currency', 3)->default('USD');
    
    // Exchange rate snapshot - EDITABLE while quote is active
    // Locked when order is created from this quote
    $table->decimal('exchange_rate_to_base', 20, 10)->nullable();
    $table->decimal('total_in_base', 15, 2)->nullable(); // Converted to request base currency

    $table->integer('lead_time_days')->nullable();
    $table->date('valid_until')->nullable();
    $table->text('notes')->nullable();
    $table->date('received_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    // NO unique constraint - multiple supplier quotes per project allowed
    $table->index('request_id');
    $table->index(['supplier_id', 'status']);
});
```

---

### 5.2 supplier_quote_items

```php
Schema::create('supplier_quote_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_quote_id')->constrained()->cascadeOnDelete();
    $table->foreignId('request_item_id')->nullable()->constrained(); // Traceability to original request
    $table->foreignId('article_id')->nullable()->constrained('articles');
    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);
    $table->decimal('unit_price', 15, 4);
    $table->decimal('total', 15, 2);
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('supplier_quote_id');
    $table->index('request_item_id');
});
```

**Traceability:** Links supplier quote item back to the original buyer request item.

---

## 6. Buyer Quotes (with Extension Tracking)

### 6.1 buyer_quotes

```php
Schema::create('buyer_quotes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained()->cascadeOnDelete();
    $table->integer('version')->default(1);
    $table->foreignId('parent_id')->nullable()
          ->constrained('buyer_quotes')
          ->nullOnDelete();
    $table->string('quote_number', 50);             // Q-2024-0089-v1
    $table->string('status', 30)->default('draft'); // draft, sent, accepted, rejected, expired, superseded
    
    // Amounts in buyer's currency
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_percent', 5, 2)->default(0);   // Local tax %
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->char('currency', 3)->default('USD');
    
    // Validity tracking
    $table->date('valid_until')->nullable();
    $table->date('original_valid_until')->nullable();   // Track if extended
    
    // Payment terms
    $table->decimal('prepayment_percent', 5, 2)->nullable();
    $table->integer('net_days')->nullable();
    $table->text('payment_terms_notes')->nullable();
    
    $table->text('notes')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('accepted_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('request_id');
    $table->index(['request_id', 'version']);
    $table->index('status');
});
```

---

### 6.2 buyer_quote_items

Items from multiple suppliers consolidated into one buyer quote.

```php
Schema::create('buyer_quote_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_quote_id')->constrained()->cascadeOnDelete();
    $table->foreignId('request_item_id')->nullable()->constrained(); // Traceability to original request
    $table->foreignId('article_id')->nullable()->constrained('articles');
    $table->foreignId('supplier_quote_item_id')->nullable()  // Link to supplier source
          ->constrained('supplier_quote_items')
          ->nullOnDelete();
    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);

    // Cost in supplier's currency (reference, EDITABLE during negotiation)
    $table->decimal('cost_price', 15, 4);
    $table->char('cost_currency', 3)->nullable();

    // Sell price in buyer's currency (EDITABLE during negotiation)
    $table->decimal('unit_price', 15, 4);
    $table->decimal('total', 15, 2);
    $table->decimal('margin_percent', 5, 2)->nullable();

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('buyer_quote_id');
    $table->index('request_item_id');
});
```

**Traceability:** Full chain from `request_item` → `supplier_quote_item` → `buyer_quote_item`.

---

### 6.3 buyer_quote_extensions ⭐ (NEW)

Track quote validity extensions with reasons.

```php
Schema::create('buyer_quote_extensions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_quote_id')->constrained()->cascadeOnDelete();
    $table->date('previous_valid_until');
    $table->date('new_valid_until');
    $table->text('reason');                         // Required reason for extension
    $table->boolean('prices_changed')->default(false);
    $table->boolean('availability_changed')->default(false);
    $table->text('change_notes')->nullable();       // What changed if anything
    $table->foreignId('extended_by')->constrained('users');
    $table->timestamps();
    
    $table->index('buyer_quote_id');
});
```

**UI Mapping:** "Extend Quote" button → Modal with reason field

---

## 7. Order Tables

### 7.1 buyer_orders (One per Project)

Consolidated order from buyer.

```php
Schema::create('buyer_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained();
    $table->foreignId('buyer_quote_id')->constrained('buyer_quotes');
    $table->string('po_number', 100)->nullable();   // Buyer's PO ref
    $table->string('order_number', 50)->unique();   // BO-2024-0001
    $table->string('status', 30)->default('confirmed');
    
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_percent', 5, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->char('currency', 3)->default('USD');
    
    // LOCKED payment terms
    $table->decimal('prepayment_percent', 5, 2)->nullable();
    $table->integer('net_days')->nullable();
    $table->text('payment_terms_notes')->nullable();
    
    $table->date('expected_delivery')->nullable();
    $table->text('notes')->nullable();
    $table->date('received_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->unique('request_id');                   // Still 1 buyer order per project
    $table->index('status');
});
```

---

### 7.2 buyer_order_items

```php
Schema::create('buyer_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles');
    $table->foreignId('supplier_id')->nullable()->constrained(); // Which supplier fulfills this
    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);
    $table->decimal('unit_price', 15, 4);
    $table->decimal('total', 15, 2);
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index('buyer_order_id');
    $table->index('supplier_id');
});
```

---

### 7.3 supplier_orders (Multiple per Project)

```php
Schema::create('supplier_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained();
    $table->foreignId('supplier_id')->constrained();
    $table->foreignId('supplier_quote_id')->constrained('supplier_quotes');
    $table->string('po_number', 50)->unique();      // PO-2024-0001
    $table->string('status', 30)->default('draft');
    
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->char('currency', 3)->default('USD');
    
    // Exchange rate at time of order
    $table->decimal('exchange_rate_to_base', 20, 10)->nullable();
    $table->decimal('total_in_base', 15, 2)->nullable();
    
    $table->string('payment_terms', 100)->nullable();
    $table->date('expected_delivery')->nullable();
    $table->date('actual_delivery')->nullable();
    $table->text('notes')->nullable();
    $table->date('sent_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    // NO unique constraint - multiple supplier orders per project allowed
    $table->index('request_id');
    $table->index('supplier_id');
    $table->index('status');
});
```

---

### 7.4 supplier_order_items

```php
Schema::create('supplier_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles');
    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);
    $table->decimal('unit_price', 15, 4);
    $table->decimal('total', 15, 2);
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index('supplier_order_id');
});
```

---

## 8. Invoice & Payment Tables

### 8.1 buyer_invoices

```php
Schema::create('buyer_invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained();
    $table->foreignId('buyer_order_id')->constrained('buyer_orders');
    $table->string('invoice_number', 50)->unique();
    $table->string('type', 30)->default('standard'); // prepayment, balance, standard
    $table->string('status', 30)->default('draft');
    
    $table->decimal('subtotal', 15, 2);
    $table->decimal('tax_percent', 5, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('amount', 15, 2);               // Total including tax
    $table->char('currency', 3)->default('USD');
    
    $table->date('issued_at')->nullable();
    $table->date('due_at')->nullable();
    $table->date('paid_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('request_id');
    $table->index(['status', 'due_at']);
});
```

---

### 8.2 buyer_payments

```php
Schema::create('buyer_payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_invoice_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->char('currency', 3)->default('USD');
    
    // If paid in different currency
    $table->decimal('original_amount', 15, 2)->nullable();
    $table->char('original_currency', 3)->nullable();
    $table->decimal('exchange_rate', 20, 10)->nullable();
    
    $table->date('paid_at');
    $table->string('method', 50);
    $table->string('reference', 100)->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('recorded_by')->nullable()->constrained('users');
    $table->timestamps();
    
    $table->index('buyer_invoice_id');
    $table->index('paid_at');
});
```

---

### 8.3 supplier_invoices (Multiple per Project)

```php
Schema::create('supplier_invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained();
    $table->foreignId('supplier_order_id')->constrained('supplier_orders');
    $table->foreignId('supplier_id')->constrained();
    $table->string('invoice_number', 100);
    $table->string('status', 30)->default('received');
    
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('amount', 15, 2);
    $table->char('currency', 3)->default('USD');
    
    // Exchange rate snapshot
    $table->decimal('exchange_rate_to_base', 20, 10)->nullable();
    $table->decimal('amount_in_base', 15, 2)->nullable();
    
    $table->date('received_at')->nullable();
    $table->date('due_at')->nullable();
    $table->date('paid_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('request_id');
    $table->index('supplier_id');
    $table->index(['status', 'due_at']);
});
```

---

### 8.4 supplier_payments

```php
Schema::create('supplier_payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->char('currency', 3)->default('USD');
    
    // If paid in different currency than invoice
    $table->decimal('original_amount', 15, 2)->nullable();
    $table->char('original_currency', 3)->nullable();
    $table->decimal('exchange_rate', 20, 10)->nullable();
    
    $table->date('paid_at');
    $table->string('method', 50);
    $table->string('reference', 100)->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('recorded_by')->nullable()->constrained('users');
    $table->timestamps();
    
    $table->index('supplier_invoice_id');
});
```

---

## 9. Shipments (Manual Journaling)

### 9.1 shipments

**Manual journaling approach.** Each supplier uses their own shipper - we record what they tell us, not manage logistics. Priority: status updates, tracking reference, carrier info.

```php
Schema::create('shipments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained()->cascadeOnDelete();
    $table->foreignId('supplier_order_id')->nullable()
          ->constrained('supplier_orders');
    $table->string('type', 30);                     // inbound, outbound

    // STATUS IS PRIMARY - manually updated based on supplier/shipper info
    $table->string('status', 30)->default('pending'); // pending, in_transit, delivered, partial, failed

    // Tracking reference (for lookup, not live integration)
    $table->string('carrier', 100)->nullable();     // Supplier's shipper (JNE, JNT, SiCepat, etc.)
    $table->string('tracking_number', 100)->nullable();

    // Dates (manually recorded)
    $table->date('shipped_at')->nullable();
    $table->date('expected_delivery')->nullable();
    $table->date('delivered_at')->nullable();

    $table->text('notes')->nullable();              // Journal entry notes
    $table->foreignId('recorded_by')->nullable()->constrained('users');
    $table->timestamps();

    $table->index('request_id');
    $table->index('supplier_order_id');
    $table->index(['type', 'status']);
    $table->index('tracking_number');
});
```

**Workflow:**
1. Supplier ships goods, sends tracking info (WA/email)
2. Admin creates shipment record with carrier + tracking number
3. Admin updates status as supplier/shipper provides updates
4. On receipt, admin records what was actually received

---

### 9.2 shipment_items

Tracks what was actually received vs ordered. Essential for discrepancy detection.

```php
Schema::create('shipment_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('supplier_order_item_id')->constrained()->cascadeOnDelete();

    $table->decimal('quantity_shipped', 15, 3);     // What supplier says they shipped
    $table->decimal('quantity_received', 15, 3)->nullable(); // What we actually received
    $table->string('condition', 30)->default('good'); // good, damaged, rejected

    $table->text('notes')->nullable();              // Discrepancy notes
    $table->timestamps();

    $table->index('shipment_id');
    $table->index('supplier_order_item_id');
});
```

**Why this matters:**
- Ordered 100, supplier says shipped 100, but we received 95 → shortage claim
- Received 100, but 5 damaged → supplier dispute
- Partial shipments: Ship 50 now, 50 next week

---

## 10. Attachments (Polymorphic)

### 10.1 attachments

```php
Schema::create('attachments', function (Blueprint $table) {
    $table->id();
    $table->morphs('attachable');
    $table->string('type', 50);                     // payment_proof, shipping_doc, pod, invoice_copy, quote_doc, other
    $table->string('original_name', 255);
    $table->string('file_path', 500);
    $table->string('mime_type', 100)->nullable();
    $table->unsignedBigInteger('file_size')->nullable();
    $table->text('description')->nullable();
    $table->foreignId('uploaded_by')->nullable()->constrained('users');
    $table->timestamps();
    
    $table->index(['attachable_type', 'attachable_id']);
    $table->index('type');
});
```

---

## 11. Activity Log

### 11.1 request_activities

```php
Schema::create('request_activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('type', 50);
    $table->text('description');
    $table->jsonb('properties')->nullable();
    $table->timestamp('created_at');
    
    $table->index(['request_id', 'created_at']);
});
```

---

## 12. Roles & Permissions

### 12.1 roles

```php
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name', 50)->unique();           // superadmin, admin, sales, finance, viewer
    $table->string('display_name', 100);
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**Seed:**
```php
[
    ['name' => 'superadmin', 'display_name' => 'Super Administrator'],
    ['name' => 'admin', 'display_name' => 'Administrator'],
    ['name' => 'sales', 'display_name' => 'Sales'],
    ['name' => 'finance', 'display_name' => 'Finance'],
    ['name' => 'viewer', 'display_name' => 'View Only'],
]
```

---

### 12.2 permissions

```php
Schema::create('permissions', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();          // projects.create, quotes.send, payments.record
    $table->string('display_name', 150);
    $table->string('group', 50)->nullable();        // projects, quotes, orders, finance, settings
    $table->timestamps();
});
```

---

### 12.3 role_permissions (Pivot)

```php
Schema::create('role_permissions', function (Blueprint $table) {
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
    
    $table->primary(['role_id', 'permission_id']);
});
```

---

### 12.4 user_roles (Pivot)

```php
Schema::create('user_roles', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    
    $table->primary(['user_id', 'role_id']);
});
```

---

## 13. Updated Settings

### 13.1 settings

Application-wide settings.

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key', 100)->unique();
    $table->text('value')->nullable();
    $table->string('type', 30)->default('string');  // string, integer, boolean, json
    $table->timestamps();
});
```

**Default settings:**
```php
['key' => 'default_currency', 'value' => 'USD'],
['key' => 'default_tax_percent', 'value' => '11'],
['key' => 'quote_validity_days', 'value' => '14'],
['key' => 'company_name', 'value' => 'Your Company'],
['key' => 'company_address', 'value' => ''],
['key' => 'invoice_prefix', 'value' => 'INV'],
['key' => 'po_prefix', 'value' => 'PO'],
```

---

## 14. Multi-Supplier Request Flow

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  REQUEST: REQ-2024-0089 "Factory Equipment"                                          │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                      │
│  SUPPLIER SIDE (Multiple)                       BUYER SIDE (Consolidated)           │
│  ════════════════════════                       ══════════════════════════          │
│                                                                                      │
│  ┌─────────────────────┐                                                            │
│  │ Supplier Quote #1   │                                                            │
│  │ MotorCorp (IDR)     │───┐                                                        │
│  │ - Industrial Motor  │   │                                                        │
│  │ Rp 75,000,000       │   │                    ┌─────────────────────┐            │
│  └─────────────────────┘   │                    │ BUYER QUOTE (USD)   │            │
│                            ├───────────────────>│                     │            │
│  ┌─────────────────────┐   │                    │ - Motor    $5,200   │            │
│  │ Supplier Quote #2   │   │                    │ - Panel    $2,400   │            │
│  │ ElectroParts (USD)  │───┤                    │ - Sensors    $950   │            │
│  │ - Control Panel     │   │                    │ - Hardware   $380   │            │
│  │ - Safety Sensors    │   │                    │ ─────────────────── │            │
│  │ $2,800              │   │                    │ Subtotal   $8,930   │            │
│  └─────────────────────┘   │                    │ Tax (11%)    $982   │            │
│                            │                    │ TOTAL      $9,912   │            │
│  ┌─────────────────────┐   │                    └─────────────────────┘            │
│  │ Supplier Quote #3   │───┘                              │                         │
│  │ MetalWorks (IDR)    │                                  ▼                         │
│  │ - Mounting Hardware │                        ┌─────────────────────┐            │
│  │ Rp 4,500,000        │                        │ BUYER ORDER         │            │
│  └─────────────────────┘                        │ (1 consolidated)    │            │
│           │                                     └─────────────────────┘            │
│           ▼                                               │                         │
│  ┌─────────────────────┐                                  ▼                         │
│  │ Supplier Order #1   │                        ┌─────────────────────┐            │
│  │ → MotorCorp         │                        │ BUYER INVOICE       │            │
│  └─────────────────────┘                        │ (1 or split)        │            │
│  ┌─────────────────────┐                        └─────────────────────┘            │
│  │ Supplier Order #2   │                                                            │
│  │ → ElectroParts      │                                                            │
│  └─────────────────────┘                                                            │
│  ┌─────────────────────┐                                                            │
│  │ Supplier Order #3   │                                                            │
│  │ → MetalWorks        │                                                            │
│  └─────────────────────┘                                                            │
│           │                                                                         │
│           ▼                                                                         │
│  ┌─────────────────────┐                                                            │
│  │ 3 Supplier Invoices │                                                            │
│  │ 3 Supplier Payments │                                                            │
│  │ 3 Inbound Shipments │                                                            │
│  └─────────────────────┘                                                            │
│                                                                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 15. Migration Order

```bash
# 1. Base tables
create_currencies_table
create_exchange_rates_table
create_tags_table
create_roles_table
create_permissions_table
create_role_permissions_table
create_user_roles_table
create_settings_table

# 2. Master data
create_buyers_table
create_suppliers_table
create_articles_table
create_taggables_table
create_supplier_articles_table

# 3. Requests & Projects
create_projects_table           # Create first (parent for requests)
create_requests_table
create_request_items_table

# 4. Supplier side (multiple)
create_supplier_quotes_table
create_supplier_quote_items_table
create_supplier_orders_table
create_supplier_order_items_table
create_supplier_invoices_table
create_supplier_payments_table

# 5. Buyer side (consolidated)
create_buyer_quotes_table
create_buyer_quote_items_table
create_buyer_quote_extensions_table
create_buyer_orders_table
create_buyer_order_items_table
create_buyer_invoices_table
create_buyer_payments_table

# 6. Supporting
create_shipments_table
create_shipment_items_table
create_attachments_table
create_request_activities_table
```

---

## 16. Value Locking Rules

**Quotes are the negotiation phase.** All values remain editable while a quote is active.

**Orders lock values.** Once a quote becomes an order, values are frozen for accounting integrity.

| Field | On Quote | On Order |
|-------|----------|----------|
| Exchange rate | ✏️ Editable | 🔒 Locked |
| Unit prices | ✏️ Editable | 🔒 Locked |
| Quantities | ✏️ Editable | 🔒 Locked |
| Tax percent | ✏️ Editable | 🔒 Locked |
| Payment terms | ✏️ Editable | 🔒 Locked |

**Why this matters:**
- Quotes can be revised multiple times during negotiation
- Exchange rates can fluctuate; update until deal is confirmed
- Once buyer PO is received and supplier POs issued, values must not change
- Order amendments require creating new documents (credit notes, revised POs)

---

## Summary of Changes from v1

| Change | Before | After |
|--------|--------|-------|
| Terminology | Project (atomic) | Request (atomic); Project = optional grouping |
| Categories | Hierarchical `product_categories` table | Flat `tags` with polymorphic `taggables` (UI: "Categories") |
| Request items | Articles required upfront | Vague capture → match articles during sourcing |
| Traceability | No item linking | `request_item_id` on quote items for full chain |
| Supplier per request | 1 selected supplier | Multiple suppliers |
| Supplier quotes | 1 selected quote | Multiple quotes from multiple suppliers |
| Supplier orders | 1 per request | Multiple per request |
| Supplier invoices | 1 per request | Multiple per request |
| Shipments | 1 per request, no items | Multiple per request with `shipment_items` for discrepancy tracking |
| Quote expiration | Cancel/reject | Extend with reason logging |
| Value locking | Not defined | Editable on quotes, locked on orders |
| Currency | Single | Single base currency for sales; multi-currency for purchases |
| Tax | Not included | Local tax percentage on quotes/orders/invoices |
| Roles | Not included | Full RBAC structure |
| Supplier visibility | Shown to buyer | Hidden from buyer (internal only) |

---

## 17. Supplier Confidentiality

**IMPORTANT:** The `supplier_quote_item_id` on `buyer_quote_items` and `supplier_id` on `buyer_order_items` are for **INTERNAL USE ONLY**.

- These fields enable margin calculation and internal tracking
- Buyer-facing exports (PDF quotes, invoices) NEVER include supplier info
- API responses to buyers should exclude supplier references
- This protects supplier relationships and the middleman business model
