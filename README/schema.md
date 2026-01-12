# ERP Trading System - Database Schema
## Laravel Migration Reference

> **Platform:** Laravel 12 + Filament 4 + PostgreSQL
> **Base:** Relaticle CRM (team-based multi-tenancy, custom fields, Spatie packages)
> **Tables:** 30 new tables
> **Version history:** See version.md

---

## Reusing Relaticle Infrastructure

### Already Available (DO NOT CREATE)

| Component | Existing | How to Use |
|-----------|----------|------------|
| File attachments | `media` table (Spatie Media Library) | Add `HasMedia` trait to models |
| Settings | Spatie Settings package | Create `ErpSettings` class |
| Custom fields | `relaticle/custom-fields` package | Add `UsesCustomFields` trait |
| Tasks/Notes | `taskables`/`noteables` polymorphic | Add to morphMap in AppServiceProvider |
| Team scoping | `HasTeam` trait | Add trait to all ERP models |
| Creator tracking | `HasCreator` trait | Add trait to all ERP models |
| Soft deletes | Laravel `SoftDeletes` | Add trait to business entities |

### To Install

```bash
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
```

---

## Schema Overview

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              ENTITY RELATIONSHIP DIAGRAM                             │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                      │
│                           ┌──────────┐                                              │
│                           │   TAGS   │ (categories)                                 │
│                           └──────────┘                                              │
│                                │                                                     │
│                    ┌───────────┴───────────┐                                        │
│                    ▼                       ▼                                        │
│               ┌─────────┐            ┌──────────┐                                   │
│               │ARTICLES │            │SUPPLIERS │──┐                                │
│               └─────────┘            └──────────┘  │                                │
│                    │                      │        │ optional                       │
│                    └──────────┬───────────┘        │ company_id                     │
│                               ▼                    ▼                                │
│  ┌─────────┐  ┌─────────┐  ┌─────────────┐   ┌───────────┐                         │
│  │ BUYERS  │─>│PROJECTS │─>│  REQUESTS   │   │ COMPANIES │ (existing CRM)          │
│  └─────────┘  │(optional│  └─────────────┘   └───────────┘                         │
│       │       │grouping)│       │                                                   │
│       │       └─────────┘       │                                                   │
│       │ optional                │                                                   │
│       │ company_id      ┌───────┼────────┬─────────────┐                           │
│       ▼                 ▼       ▼        ▼             ▼                           │
│  ┌───────────┐   ┌─────────────┐  ┌────────────┐ ┌──────────┐                      │
│  │ COMPANIES │   │  SUPPLIER   │  │   BUYER    │ │ SHIPMENTS│                      │
│  │  (CRM)    │   │   QUOTES    │  │   QUOTES   │ │(multiple)│                      │
│  └───────────┘   │ (multiple)  │  │(versioned) │ └──────────┘                      │
│                  └─────────────┘  └────────────┘                                   │
│                         │                │                                          │
│                         ▼                ▼                                          │
│                  ┌─────────────┐  ┌────────────┐                                   │
│                  │  SUPPLIER   │  │   BUYER    │ (1 consolidated)                  │
│                  │   ORDERS    │  │   ORDER    │                                   │
│                  │ (multiple)  │  └────────────┘                                   │
│                  └─────────────┘        │                                          │
│                         │               │                                           │
│                         ▼               ▼                                           │
│                  ┌─────────────┐  ┌────────────┐                                   │
│                  │  SUPPLIER   │  │   BUYER    │                                   │
│                  │  INVOICES   │  │  INVOICES  │                                   │
│                  │ (multiple)  │  └────────────┘                                   │
│                  └─────────────┘        │                                          │
│                         │               │                                           │
│                         ▼               ▼                                           │
│                  ┌─────────────┐  ┌────────────┐                                   │
│                  │  SUPPLIER   │  │   BUYER    │                                   │
│                  │  PAYMENTS   │  │  PAYMENTS  │                                   │
│                  └─────────────┘  └────────────┘                                   │
│                                                                                     │
│  ┌────────────────┐    ┌─────────────────┐    ┌──────────────┐                     │
│  │ EXCHANGE_RATES │    │  MEDIA (Spatie) │    │SPATIE PERMS  │                     │
│  │  (per date)    │    │   (existing)    │    │  (package)   │                     │
│  └────────────────┘    └─────────────────┘    └──────────────┘                     │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Categories System (Internal: tags)

UI displays "Categories" but the internal table name remains `tags`.

### 1.1 tags

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->string('name', 100);
    $table->string('slug', 100);
    $table->string('color', 7)->nullable();
    $table->timestamps();

    $table->unique(['team_id', 'slug']);
    $table->index('slug');
});
```

### 1.2 taggables (Polymorphic Pivot)

```php
Schema::create('taggables', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->morphs('taggable');
    $table->timestamps();

    $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
});
```

---

## 2. Currency & Exchange Rates

### 2.1 currencies

```php
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->char('code', 3)->unique();
    $table->string('name', 100);
    $table->string('symbol', 10);
    $table->integer('decimal_places')->default(2);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2.2 exchange_rates

```php
Schema::create('exchange_rates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->char('base_currency', 3);
    $table->char('target_currency', 3);
    $table->decimal('rate', 20, 10);
    $table->date('effective_date');
    $table->string('source', 100)->nullable();
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique(['team_id', 'base_currency', 'target_currency', 'effective_date'], 'exchange_rates_unique');
    $table->index('effective_date');
});
```

### 2.3 tax_codes ⭐ NEW

Tax codes for the dropdown selector. Both buyer and supplier line items reference this.

```php
Schema::create('tax_codes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->string('code', 20);                  // PPN11, VAT0, EXEMPT, N/A
    $table->string('name', 100);                 // "PPN 11%", "Zero Rate", "Exempt", "No Tax"
    $table->decimal('rate', 5, 2);               // 11.00, 0.00, 0.00, 0.00
    $table->boolean('is_inclusive_default')->default(false); // Default checkbox state
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false); // One default per team
    $table->integer('sort_order')->default(0);
    $table->timestamps();

    $table->unique(['team_id', 'code']);
    $table->index(['team_id', 'is_active']);
});
```

**Seed with common tax codes:**
```php
[
    ['code' => 'PPN11', 'name' => 'PPN 11%', 'rate' => 11.00, 'is_default' => true],
    ['code' => 'PPN0', 'name' => 'PPN 0%', 'rate' => 0.00],
    ['code' => 'EXEMPT', 'name' => 'Tax Exempt', 'rate' => 0.00],
    ['code' => 'NOTAX', 'name' => 'No Tax', 'rate' => 0.00],
]
```

**UI Usage:**
- Dropdown shows: "PPN 11%", "PPN 0%", "Tax Exempt", "No Tax"
- Checkbox: "Price includes tax" (is_tax_inclusive)
- System calculates net/gross automatically

---

## 3. Master Data Tables

### 3.1 buyers

**WITH optional CRM company linking**

```php
Schema::create('buyers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

    // OPTIONAL link to CRM company (for contact sync)
    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

    $table->string('code', 20);
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

    $table->unique(['team_id', 'code']);
    $table->index('is_on_hold');
});
```

**Model traits:** `HasTeam`, `HasCreator`, `UsesCustomFields`, `SoftDeletes`

### 3.2 suppliers

**WITH optional CRM company linking**

```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

    // OPTIONAL link to CRM company (for contact sync)
    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

    $table->string('code', 20);
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

    $table->unique(['team_id', 'code']);
});
```

**Model traits:** `HasTeam`, `HasCreator`, `HasTags`, `UsesCustomFields`, `SoftDeletes`

### 3.3 articles

```php
Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

    // Default tax code for this article (can be overridden on line items)
    $table->foreignId('default_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();

    $table->string('name', 255);
    $table->string('sku', 50)->nullable();
    $table->text('description')->nullable();
    $table->string('unit', 50);
    $table->jsonb('attributes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['team_id', 'sku']);
    $table->index(['attributes'], 'articles_attributes_gin')->algorithm('gin');
});
```

**Model traits:** `HasTeam`, `HasCreator`, `HasTags`, `UsesCustomFields`, `SoftDeletes`

**Tax behavior:** When adding an article to a quote/order, the `default_tax_code_id` is used as the initial value for the line item's tax dropdown, but can be changed.

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

### 4.1 projects

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('buyer_id')->constrained('buyers');
    $table->string('project_number', 20);
    $table->string('name', 255);
    $table->text('description')->nullable();
    $table->string('status', 50)->default('active');
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['team_id', 'project_number']);
    $table->index('buyer_id');
    $table->index('status');
});
```

### 4.2 requests

```php
Schema::create('requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
    $table->foreignId('buyer_id')->constrained('buyers');
    $table->string('request_number', 20);
    $table->string('title', 255);
    $table->string('stage', 50)->default('new');
    $table->text('requirements')->nullable();
    $table->char('base_currency', 3)->default('USD');
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['team_id', 'request_number']);
    $table->index('buyer_id');
    $table->index('project_id');
    $table->index('stage');
    $table->index('created_at');
});
```

**Model traits:** `HasTeam`, `HasCreator`, `UsesCustomFields`, `HasMedia`, `SoftDeletes`

**Polymorphic relations:** Add to morphMap for `tasks()` and `notes()` support.

### 4.3 request_items

```php
Schema::create('request_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
    $table->unsignedInteger('sort_order')->default(0);    // ⭐ NEW: For reordering
    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);
    $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
    $table->timestamp('matched_at')->nullable();
    $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index('request_id');
    $table->index('article_id');
});
```

---

## 5. Supplier Quotes

### 5.1 supplier_quotes (Header)

```php
Schema::create('supplier_quotes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
    $table->foreignId('supplier_id')->constrained('suppliers');
    $table->string('quote_number', 50)->nullable();
    $table->string('status', 30)->default('pending'); // pending, selected, rejected, expired

    // ⭐ Default tax code for new items (can be overridden per item)
    $table->foreignId('default_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();

    // Currency & exchange rate
    $table->char('currency', 3)->default('USD');
    $table->decimal('exchange_rate_to_base', 20, 10)->nullable();

    // Totals (calculated from line items)
    $table->decimal('subtotal', 15, 2)->default(0);       // Sum of line subtotals (exc tax)
    $table->decimal('tax_total', 15, 2)->default(0);      // Sum of line tax_amounts
    $table->decimal('total', 15, 2)->default(0);          // subtotal + tax_total
    $table->decimal('total_in_base', 15, 2)->nullable();  // Converted to request base currency

    $table->integer('lead_time_days')->nullable();
    $table->date('valid_until')->nullable();
    $table->date('received_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index('request_id');
    $table->index(['supplier_id', 'status']);
});
```

### 5.2 supplier_quote_items ⭐ UPDATED

```php
Schema::create('supplier_quote_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_quote_id')->constrained()->cascadeOnDelete();
    $table->foreignId('request_item_id')->nullable()->constrained('request_items')->nullOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);    // ⭐ NEW

    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);

    // ⭐ TAX HANDLING (per line item)
    $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
    $table->boolean('is_tax_inclusive')->default(false);  // Checkbox: price entered inc/exc tax
    $table->decimal('tax_rate', 5, 2)->default(0);        // Snapshot of rate at entry

    // Pricing
    $table->decimal('unit_price', 15, 4);                 // User-entered price (as-is)
    $table->decimal('unit_price_exc_tax', 15, 4);         // Calculated: net unit price
    $table->decimal('subtotal', 15, 2);                   // qty × unit_price_exc_tax
    $table->decimal('tax_amount', 15, 2)->default(0);     // Tax for this line
    $table->decimal('total', 15, 2);                      // subtotal + tax_amount

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('supplier_quote_id');
    $table->index('request_item_id');
});
```

**Calculation Logic:**
```php
if ($is_tax_inclusive) {
    $unit_price_exc_tax = $unit_price / (1 + $tax_rate / 100);
} else {
    $unit_price_exc_tax = $unit_price;
}
$subtotal = $quantity * $unit_price_exc_tax;
$tax_amount = $subtotal * ($tax_rate / 100);
$total = $subtotal + $tax_amount;
```

---

## 6. Buyer Quotes

### 6.1 buyer_quotes (Header)

```php
Schema::create('buyer_quotes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
    $table->integer('version')->default(1);
    $table->foreignId('parent_id')->nullable()->constrained('buyer_quotes')->nullOnDelete();
    $table->string('quote_number', 50);
    $table->string('status', 30)->default('draft'); // draft, sent, accepted, rejected, expired, superseded
    $table->char('currency', 3)->default('USD');

    // ⭐ Default tax code for new items (can be overridden per item)
    $table->foreignId('default_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();

    // Totals (calculated from line items)
    $table->decimal('subtotal', 15, 2)->default(0);       // Sum of line subtotals
    $table->decimal('tax_total', 15, 2)->default(0);      // Sum of line tax_amounts
    $table->decimal('total', 15, 2)->default(0);          // subtotal + tax_total

    // Validity
    $table->date('valid_until')->nullable();
    $table->date('original_valid_until')->nullable();

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

### 6.2 buyer_quote_items ⭐ UPDATED

```php
Schema::create('buyer_quote_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_quote_id')->constrained()->cascadeOnDelete();
    $table->foreignId('request_item_id')->nullable()->constrained('request_items')->nullOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
    $table->foreignId('supplier_quote_item_id')->nullable()->constrained('supplier_quote_items')->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);    // ⭐ NEW

    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);

    // Cost reference (from supplier - internal only)
    $table->decimal('cost_price', 15, 4)->nullable();
    $table->char('cost_currency', 3)->nullable();

    // ⭐ TAX HANDLING (per line item)
    $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
    $table->boolean('is_tax_inclusive')->default(false);
    $table->decimal('tax_rate', 5, 2)->default(0);

    // Sell pricing
    $table->decimal('unit_price', 15, 4);                 // User-entered sell price
    $table->decimal('unit_price_exc_tax', 15, 4);         // Calculated net
    $table->decimal('subtotal', 15, 2);                   // qty × unit_price_exc_tax
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2);                      // subtotal + tax_amount

    // Margin (calculated)
    $table->decimal('margin_percent', 5, 2)->nullable();

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('buyer_quote_id');
    $table->index('request_item_id');
});
```

### 6.3 buyer_quote_extensions

```php
Schema::create('buyer_quote_extensions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_quote_id')->constrained()->cascadeOnDelete();
    $table->date('previous_valid_until');
    $table->date('new_valid_until');
    $table->text('reason');
    $table->boolean('prices_changed')->default(false);
    $table->boolean('availability_changed')->default(false);
    $table->text('change_notes')->nullable();
    $table->foreignId('extended_by')->constrained('users');
    $table->timestamps();

    $table->index('buyer_quote_id');
});
```

---

## 7. Orders

### 7.1 buyer_orders (Header)

```php
Schema::create('buyer_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('request_id')->constrained('requests');
    $table->foreignId('buyer_quote_id')->constrained('buyer_quotes');
    $table->string('po_number', 100)->nullable();    // Buyer's PO ref
    $table->string('order_number', 50);              // BO-2024-0001
    $table->string('status', 30)->default('confirmed');
    $table->char('currency', 3)->default('USD');

    // ⭐ Default tax code (LOCKED from quote)
    $table->foreignId('default_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();

    // Totals (LOCKED from quote)
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_total', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);

    // Payment terms (LOCKED from quote)
    $table->decimal('prepayment_percent', 5, 2)->nullable();
    $table->integer('net_days')->nullable();
    $table->text('payment_terms_notes')->nullable();

    $table->date('expected_delivery')->nullable();
    $table->date('received_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['team_id', 'order_number']);
    $table->unique('request_id');
    $table->index('status');
});
```

### 7.2 buyer_order_items ⭐ UPDATED

```php
Schema::create('buyer_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
    $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);    // ⭐ NEW

    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);

    // ⭐ TAX (LOCKED from quote)
    $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
    $table->boolean('is_tax_inclusive')->default(false);
    $table->decimal('tax_rate', 5, 2)->default(0);

    $table->decimal('unit_price', 15, 4);
    $table->decimal('unit_price_exc_tax', 15, 4);
    $table->decimal('subtotal', 15, 2);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2);

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('buyer_order_id');
    $table->index('supplier_id');
});
```

### 7.3 supplier_orders (Header)

```php
Schema::create('supplier_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('request_id')->constrained('requests');
    $table->foreignId('supplier_id')->constrained('suppliers');
    $table->foreignId('supplier_quote_id')->constrained('supplier_quotes');
    $table->string('po_number', 50);                 // PO-2024-0001-A
    $table->string('status', 30)->default('draft');
    $table->char('currency', 3)->default('USD');

    // ⭐ Default tax code (LOCKED from supplier quote)
    $table->foreignId('default_tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();

    // Exchange rate (LOCKED at order time)
    $table->decimal('exchange_rate_to_base', 20, 10)->nullable();

    // Totals
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_total', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->decimal('total_in_base', 15, 2)->nullable();

    $table->string('payment_terms', 100)->nullable();
    $table->date('expected_delivery')->nullable();
    $table->date('actual_delivery')->nullable();
    $table->date('sent_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['team_id', 'po_number']);
    $table->index('request_id');
    $table->index('supplier_id');
    $table->index('status');
});
```

### 7.4 supplier_order_items ⭐ UPDATED

```php
Schema::create('supplier_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);    // ⭐ NEW

    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);
    $table->string('unit', 50);

    // ⭐ TAX (LOCKED from supplier quote)
    $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
    $table->boolean('is_tax_inclusive')->default(false);
    $table->decimal('tax_rate', 5, 2)->default(0);

    $table->decimal('unit_price', 15, 4);
    $table->decimal('unit_price_exc_tax', 15, 4);
    $table->decimal('subtotal', 15, 2);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2);

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('supplier_order_id');
});
```

---

## 8. Invoices & Payments

### 8.1 buyer_invoices ⭐ UPDATED

```php
Schema::create('buyer_invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('request_id')->constrained('requests');
    $table->foreignId('buyer_order_id')->constrained('buyer_orders');
    $table->string('invoice_number', 50);
    $table->string('type', 30)->default('standard'); // prepayment, balance, standard, credit_note, debit_note
    $table->string('status', 30)->default('draft');  // draft, sent, partial, paid, overdue, cancelled
    $table->char('currency', 3)->default('USD');

    // ⭐ Credit note support
    $table->foreignId('original_invoice_id')->nullable()->constrained('buyer_invoices')->nullOnDelete();
    $table->text('credit_reason')->nullable();       // Required for credit notes

    $table->decimal('subtotal', 15, 2);
    $table->decimal('tax_total', 15, 2)->default(0);
    $table->decimal('amount', 15, 2);                // Total including tax (negative for credit notes)

    $table->date('issued_at')->nullable();
    $table->date('due_at')->nullable();
    $table->date('paid_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['team_id', 'invoice_number']);
    $table->index('request_id');
    $table->index('original_invoice_id');
    $table->index(['status', 'due_at']);
});
```

### 8.2 buyer_invoice_items ⭐ NEW

```php
Schema::create('buyer_invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('buyer_invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('buyer_order_item_id')->nullable()->constrained('buyer_order_items')->nullOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);

    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);              // Negative for credit notes
    $table->string('unit', 50);

    // ⭐ TAX (copied from order item)
    $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
    $table->boolean('is_tax_inclusive')->default(false);
    $table->decimal('tax_rate', 5, 2)->default(0);

    $table->decimal('unit_price', 15, 4);
    $table->decimal('unit_price_exc_tax', 15, 4);
    $table->decimal('subtotal', 15, 2);              // Negative for credit notes
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2);                 // Negative for credit notes

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('buyer_invoice_id');
    $table->index('buyer_order_item_id');
});
```

### 8.3 buyer_payments

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
    $table->string('method', 50);                    // bank_transfer, cash, check, lc
    $table->string('reference', 100)->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index('buyer_invoice_id');
    $table->index('paid_at');
});
```

**Implements `HasMedia`** for `payment_proof` collection.

### 8.4 supplier_invoices ⭐ UPDATED

```php
Schema::create('supplier_invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('request_id')->constrained('requests');
    $table->foreignId('supplier_order_id')->constrained('supplier_orders');
    $table->foreignId('supplier_id')->constrained('suppliers');
    $table->string('invoice_number', 100);
    $table->string('type', 30)->default('standard'); // standard, credit_note, debit_note
    $table->string('status', 30)->default('received'); // received, approved, paid, disputed
    $table->char('currency', 3)->default('USD');

    // ⭐ Credit note support
    $table->foreignId('original_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
    $table->text('credit_reason')->nullable();       // Required for credit notes

    // Exchange rate snapshot
    $table->decimal('exchange_rate_to_base', 20, 10)->nullable();

    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_total', 15, 2)->default(0);
    $table->decimal('amount', 15, 2);                // Negative for credit notes
    $table->decimal('amount_in_base', 15, 2)->nullable();

    $table->date('received_at')->nullable();
    $table->date('due_at')->nullable();
    $table->date('paid_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index('request_id');
    $table->index('supplier_id');
    $table->index('original_invoice_id');
    $table->index(['status', 'due_at']);
});
```

### 8.5 supplier_invoice_items ⭐ NEW

```php
Schema::create('supplier_invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('supplier_order_item_id')->nullable()->constrained('supplier_order_items')->nullOnDelete();
    $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
    $table->unsignedInteger('sort_order')->default(0);

    $table->string('description', 500);
    $table->decimal('quantity', 15, 3);              // Negative for credit notes
    $table->string('unit', 50);

    // ⭐ TAX (from supplier invoice)
    $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
    $table->boolean('is_tax_inclusive')->default(false);
    $table->decimal('tax_rate', 5, 2)->default(0);

    $table->decimal('unit_price', 15, 4);
    $table->decimal('unit_price_exc_tax', 15, 4);
    $table->decimal('subtotal', 15, 2);              // Negative for credit notes
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total', 15, 2);                 // Negative for credit notes

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('supplier_invoice_id');
    $table->index('supplier_order_item_id');
});
```

### 8.6 supplier_payments

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
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index('supplier_invoice_id');
});
```

**Implements `HasMedia`** for `payment_proof` collection.

---

## 9. Shipments

### 9.1 shipments

```php
Schema::create('shipments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();

    // For INBOUND (from supplier)
    $table->foreignId('supplier_order_id')->nullable()->constrained('supplier_orders')->nullOnDelete();

    // For OUTBOUND (to buyer) ⭐ NEW
    $table->foreignId('buyer_order_id')->nullable()->constrained('buyer_orders')->nullOnDelete();

    $table->string('type', 30);                      // inbound, outbound
    $table->string('status', 30)->default('pending'); // pending, in_transit, delivered, partial, failed

    $table->string('carrier', 100)->nullable();
    $table->string('tracking_number', 100)->nullable();

    $table->date('shipped_at')->nullable();
    $table->date('expected_delivery')->nullable();
    $table->date('delivered_at')->nullable();

    $table->text('notes')->nullable();
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index('request_id');
    $table->index('supplier_order_id');
    $table->index('buyer_order_id');
    $table->index(['type', 'status']);
    $table->index('tracking_number');
});
```

**Implements `HasMedia`** for `shipping_doc` and `pod` collections.

### 9.2 shipment_items ⭐ UPDATED

```php
Schema::create('shipment_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('sort_order')->default(0);    // ⭐ NEW

    // For INBOUND shipments (from supplier)
    $table->foreignId('supplier_order_item_id')->nullable()->constrained('supplier_order_items')->cascadeOnDelete();

    // For OUTBOUND shipments (to buyer) ⭐ NEW
    $table->foreignId('buyer_order_item_id')->nullable()->constrained('buyer_order_items')->cascadeOnDelete();

    $table->decimal('quantity_shipped', 15, 3);
    $table->decimal('quantity_received', 15, 3)->nullable();
    $table->string('condition', 30)->default('good'); // good, damaged, rejected

    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('shipment_id');
    $table->index('supplier_order_item_id');
    $table->index('buyer_order_item_id');
});
```

**Constraint:** Either `supplier_order_item_id` OR `buyer_order_item_id` must be set (not both).

---

## 10. Activity & Audit Logging

The system uses **two complementary logging approaches**:

| Log Type | Purpose | Scope | Package |
|----------|---------|-------|---------|
| `request_activities` | User-friendly timeline on Request detail | Request events only | Custom table |
| `activity_log` | System-wide audit trail for compliance | All entities | Spatie Activity Log |

### 10.1 request_activities (Request Timeline)

User-friendly activity stream shown on the Request detail page.

```php
Schema::create('request_activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('type', 50);                      // stage_change, quote_sent, payment_received, etc.
    $table->text('description');
    $table->jsonb('properties')->nullable();         // Additional context
    $table->timestamp('created_at');

    $table->index(['request_id', 'created_at']);
});
```

**Activity Types:**
- `stage_change` - Request stage transitions
- `quote_created`, `quote_sent`, `quote_accepted`, `quote_extended`
- `order_created`, `order_confirmed`
- `invoice_created`, `invoice_sent`
- `payment_received`, `payment_made`
- `shipment_created`, `shipment_delivered`
- `note_added`, `attachment_uploaded`

### 10.2 Spatie Activity Log (System Audit Trail)

**DO NOT CREATE custom `audit_logs` table.** Use Spatie Activity Log package.

```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

This creates the `activity_log` table automatically with:
- `log_name` - Categorize logs (e.g., 'erp', 'auth')
- `description` - Human-readable description
- `subject_type`, `subject_id` - The model that was changed (polymorphic)
- `causer_type`, `causer_id` - The user who made the change (polymorphic)
- `properties` - JSON with old/new values
- `created_at` - Timestamp

**Model Implementation:**

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

final class Buyer extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'email', 'credit_limit', 'is_on_hold'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Buyer {$eventName}");
    }
}
```

**Apply to ALL ERP models:**

| Model | Logged Fields |
|-------|---------------|
| Buyer | name, code, email, credit_limit, is_on_hold |
| Supplier | name, code, email, default_payment_terms |
| Article | name, sku, description, unit |
| Request | title, stage, requirements |
| BuyerQuote | status, total, valid_until |
| BuyerOrder | status, total |
| BuyerInvoice | status, amount, type |
| SupplierQuote | status, total |
| SupplierOrder | status, total |
| SupplierInvoice | status, amount, type |
| ExchangeRate | rate, effective_date |
| TaxCode | code, name, rate |

**Auth Events (in AuthServiceProvider or Listener):**

```php
// Log login/logout events
activity('auth')
    ->causedBy($user)
    ->withProperties(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
    ->log('User logged in');
```

**Querying Audit Logs:**

```php
// Get all activities for a specific model
$buyer = Buyer::find(1);
$activities = Activity::forSubject($buyer)->get();

// Get all activities by a user
$activities = Activity::causedBy($user)->get();

// Get activities with old/new values
$activity->properties['old']; // Previous values
$activity->properties['attributes']; // New values

// Filter by date range
Activity::where('created_at', '>=', $startDate)
    ->where('created_at', '<=', $endDate)
    ->get();
```

**Audit Log Admin View:**

```php
// Filament Resource for viewing audit logs
// Shows: timestamp, user, action, entity, changes
// Filters: date range, user, entity type, action type
// Export: CSV for compliance reports
```

---

## 11. File Attachments (Spatie Media Library)

**DO NOT CREATE `attachments` table.**

Use existing Spatie Media Library:

```php
// Model implementation
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
$payment->getFirstMediaUrl('payment_proof');
```

**Media collections by entity:**

| Entity | Collection | Purpose |
|--------|------------|---------|
| BuyerPayment | `payment_proof` | Bank statement, transfer confirmation |
| SupplierPayment | `payment_proof` | Payment confirmation |
| Shipment | `shipping_doc` | Bill of lading, packing list |
| Shipment | `pod` | Proof of delivery |
| SupplierQuote | `quote_doc` | Original supplier quote PDF |
| BuyerInvoice | `invoice_copy` | Invoice PDF |

---

## 12. Settings (Spatie Settings)

**DO NOT CREATE `settings` table.**

Use existing Spatie Settings:

```php
// app/Settings/ErpSettings.php
final class ErpSettings extends Settings
{
    public string $default_currency = 'USD';
    public float $default_tax_percent = 11.0;
    public int $quote_validity_days = 14;
    public string $company_name = '';
    public string $company_address = '';
    public string $invoice_prefix = 'INV';
    public string $po_prefix = 'PO';
    public string $request_prefix = 'REQ';
    public string $project_prefix = 'PRJ';

    public static function group(): string
    {
        return 'erp';
    }
}

// Usage
app(ErpSettings::class)->default_currency;
```

---

## 13. Roles & Permissions (Spatie Permission)

**DO NOT CREATE custom `roles`, `permissions`, `role_permissions`, `user_roles` tables.**

Use `spatie/laravel-permission`:

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

**Permission naming convention:**
```
erp.requests.view
erp.requests.create
erp.requests.update
erp.quotes.send
erp.orders.create
erp.payments.record
erp.settings.manage
```

**Default roles:**
- `superadmin` - All permissions
- `admin` - All ERP permissions
- `sales` - Requests, quotes, orders
- `finance` - Invoices, payments
- `viewer` - Read-only access

---

## 14. Migration Order

```bash
# Phase 0: Prerequisites
# (Spatie Permission migrations run automatically)

# Phase 1: Foundation (no deps)
create_currencies_table
create_tags_table
create_tax_codes_table           # ⭐ NEW: Tax codes dropdown

# Phase 2: Master data (depends on teams, users, companies, tax_codes)
create_exchange_rates_table
create_buyers_table              # with optional company_id
create_suppliers_table           # with optional company_id
create_articles_table            # with default_tax_code_id
create_taggables_table
create_supplier_articles_table

# Phase 3: Requests (depends on buyers)
create_projects_table
create_requests_table
create_request_items_table       # with sort_order

# Phase 4: Supplier side (with item-level tax)
create_supplier_quotes_table         # with default_tax_code_id
create_supplier_quote_items_table    # with tax_code_id, is_tax_inclusive, sort_order
create_supplier_orders_table         # with default_tax_code_id
create_supplier_order_items_table    # with tax_code_id, is_tax_inclusive, sort_order
create_supplier_invoices_table       # with original_invoice_id for credit notes
create_supplier_invoice_items_table  # ⭐ NEW: with tax, sort_order
create_supplier_payments_table

# Phase 5: Buyer side (with item-level tax)
create_buyer_quotes_table            # with default_tax_code_id
create_buyer_quote_items_table       # with tax_code_id, is_tax_inclusive, sort_order
create_buyer_quote_extensions_table
create_buyer_orders_table            # with default_tax_code_id
create_buyer_order_items_table       # with tax_code_id, is_tax_inclusive, sort_order
create_buyer_invoices_table          # with original_invoice_id for credit notes
create_buyer_invoice_items_table     # ⭐ NEW: with tax, sort_order
create_buyer_payments_table

# Phase 6: Supporting
create_shipments_table               # with buyer_order_id for outbound
create_shipment_items_table          # with buyer_order_item_id, sort_order
create_request_activities_table
```

---

## 15. Table Summary

### New Tables (30)

| Category | Tables | Count |
|----------|--------|-------|
| Foundation | tags, taggables, currencies, exchange_rates, **tax_codes** | **5** |
| Master Data | buyers, suppliers, articles, supplier_articles | 4 |
| Requests | projects, requests, request_items | 3 |
| Supplier Quoting | supplier_quotes, supplier_quote_items | 2 |
| Buyer Quoting | buyer_quotes, buyer_quote_items, buyer_quote_extensions | 3 |
| Supplier Orders | supplier_orders, supplier_order_items | 2 |
| Buyer Orders | buyer_orders, buyer_order_items | 2 |
| Finance | buyer_invoices, **buyer_invoice_items**, buyer_payments, supplier_invoices, **supplier_invoice_items**, supplier_payments | **6** |
| Shipments | shipments, shipment_items | 2 |
| Activity | request_activities | 1 |
| **Total** | | **30** |

### Reused (from Relaticle)

| Purpose | Existing Table/Package |
|---------|----------------------|
| File attachments | `media` (Spatie Media Library) |
| Settings | Spatie Settings (class-based) |
| RBAC | Spatie Permission package |
| Custom fields | `relaticle/custom-fields` |
| Team scoping | `teams` + `HasTeam` trait |
| Tasks/Notes | `tasks`, `notes`, `taskables`, `noteables` |

---

## 16. CRM Integration Points

### Buyer/Supplier → Company Link

```php
// Buyer model
public function company(): BelongsTo
{
    return $this->belongsTo(Company::class);
}

// Access CRM contacts via linked company
$buyer->company?->people;
```

### ERP Entities in Tasks/Notes

```php
// AppServiceProvider boot()
Relation::morphMap([
    // Existing CRM
    'company' => \App\Models\Company::class,
    'people' => \App\Models\People::class,
    'opportunity' => \App\Models\Opportunity::class,

    // New ERP
    'request' => \App\Models\Request::class,
    'buyer' => \App\Models\Buyer::class,
    'supplier' => \App\Models\Supplier::class,
]);
```

---

---

## 17. Tax Handling Workflow

### UI Components (per line item)

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Line Item                                                               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Description: [Industrial Motor 5HP                    ]                │
│  Quantity:    [10    ]  Unit: [pcs    ]                                │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ TAX HANDLING                                                     │   │
│  │                                                                   │   │
│  │  Tax Code: [▼ PPN 11%        ]  ← Dropdown from tax_codes table │   │
│  │            ○ PPN 11%                                             │   │
│  │            ○ PPN 0%                                              │   │
│  │            ○ Tax Exempt                                          │   │
│  │            ○ No Tax                                              │   │
│  │                                                                   │   │
│  │  [✓] Price includes tax       ← Checkbox (is_tax_inclusive)     │   │
│  │                                                                   │   │
│  │  Unit Price: [Rp 1,110,000]   ← User enters this                │   │
│  │                                                                   │   │
│  │  ─────────────────────────── (Calculated) ──────────────────    │   │
│  │  Net Price:    Rp 1,000,000   ← unit_price_exc_tax              │   │
│  │  Subtotal:     Rp 10,000,000  ← qty × net price                 │   │
│  │  Tax (11%):    Rp 1,100,000   ← tax_amount                      │   │
│  │  Total:        Rp 11,100,000  ← subtotal + tax                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Calculation Logic (in Service/Observer)

```php
final readonly class TaxCalculationService
{
    public function calculate(
        float $unitPrice,
        float $quantity,
        float $taxRate,
        bool $isTaxInclusive
    ): array {
        if ($isTaxInclusive) {
            // Price entered INCLUDES tax - extract net price
            $unitPriceExcTax = $unitPrice / (1 + $taxRate / 100);
        } else {
            // Price entered EXCLUDES tax - use as-is
            $unitPriceExcTax = $unitPrice;
        }

        $subtotal = $quantity * $unitPriceExcTax;
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        return [
            'unit_price' => $unitPrice,
            'unit_price_exc_tax' => round($unitPriceExcTax, 4),
            'subtotal' => round($subtotal, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
    }
}
```

### When Tax Code Changes

```php
// When user selects a tax code from dropdown
$taxCode = TaxCode::find($taxCodeId);
$item->tax_code_id = $taxCode->id;
$item->tax_rate = $taxCode->rate;           // Snapshot the rate
$item->is_tax_inclusive = $taxCode->is_inclusive_default;  // Use default from tax code

// Recalculate prices
$calculated = $taxCalculationService->calculate(
    $item->unit_price,
    $item->quantity,
    $item->tax_rate,
    $item->is_tax_inclusive
);
$item->fill($calculated);
$item->save();
```

### Header Totals (Calculated from Items)

```php
// In Quote/Order observer or service
$quote->subtotal = $quote->items->sum('subtotal');
$quote->tax_total = $quote->items->sum('tax_amount');
$quote->total = $quote->subtotal + $quote->tax_total;
```

### Buyer vs Supplier Tax Handling

| Aspect | Supplier Side | Buyer Side |
|--------|---------------|------------|
| **Who enters price** | Supplier invoice/quote | Your sell price |
| **Common case** | Often tax-inclusive (supplier includes tax) | Your choice |
| **Tax codes** | Same dropdown, but might differ | Same dropdown |
| **Currency** | Supplier's currency | Base currency |
| **Displayed to buyer** | No (internal only) | Yes (on quote/invoice PDF) |

### Example: Supplier Tax-Inclusive, Buyer Tax-Exclusive

```
Supplier Quote (IDR):
  Item: Industrial Motor
  Tax Code: PPN 11%
  Price Includes Tax: ✓ YES
  Unit Price: Rp 1,110,000 (inc tax)
  → Net: Rp 1,000,000
  → Tax: Rp 110,000

Buyer Quote (USD):
  Item: Industrial Motor
  Tax Code: PPN 11%
  Price Includes Tax: ✗ NO
  Unit Price: $120 (exc tax)
  → Net: $120
  → Tax: $13.20
  → Total: $133.20
```

---

*--- End of Database Schema ---*
