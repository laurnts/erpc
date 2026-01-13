# Change: Add ERP Trading System

## Why

Transform the existing CRM (Relaticle) into a full-featured ERP system for trading intermediaries/brokers operating without inventory. The existing CRM provides a solid foundation with team-based multi-tenancy, custom fields, and Filament 4 admin panel, but lacks the core trading workflow capabilities needed for request-to-payment lifecycle management.

## What Changes

### Reuse Existing Infrastructure (No New Tables)

| Component | Existing | Action |
|-----------|----------|--------|
| File Attachments | Spatie Media Library | Use `HasMedia` trait on ERP entities |
| Settings | Spatie Settings | Create `ErpSettings` class |
| Custom Fields | `relaticle/custom-fields` | Add `UsesCustomFields` to ERP entities |
| Tasks/Notes | `taskables`/`noteables` | Add ERP entities to morphMap |
| RBAC | None | **INSTALL** `spatie/laravel-permission` |
| Audit Log | None | **INSTALL** `spatie/laravel-activitylog` |

### Phase 1: Foundation (5 new tables)
- **ADDED** `tags` - Flat categories shared between Articles and Suppliers
- **ADDED** `taggables` - Polymorphic pivot for tag assignments
- **ADDED** `currencies` - Supported currencies (USD, IDR, EUR, etc.)
- **ADDED** `exchange_rates` - Historical exchange rates with manual entry
- **ADDED** `tax_codes` - Tax rate dropdown options (PPN 11%, Zero Rate, Exempt, etc.)

### Phase 2: Master Data (4 new tables)
- **ADDED** `buyers` - Buyer companies with credit_limit, on_hold status
  - Optional `company_id` FK to link with existing CRM companies
- **ADDED** `suppliers` - Supplier companies with lead_time, payment terms
  - Optional `company_id` FK to link with existing CRM companies
- **ADDED** `articles` - Products/services with JSONB attributes, default_tax_code_id
- **ADDED** `supplier_articles` - Pivot tracking which suppliers offer which articles

### Phase 3: Request Management (3 new tables)
- **ADDED** `projects` - Optional grouping for related Requests
- **ADDED** `requests` - Atomic unit: single buyer inquiry lifecycle
- **ADDED** `request_items` - Vague capture → article match workflow, sort_order

### Phase 4: Multi-Supplier Quoting (5 new tables)
- **ADDED** `supplier_quotes` - Multiple quotes per Request, multi-currency
- **ADDED** `supplier_quote_items` - Line items with item-level tax (tax_code_id, is_tax_inclusive), sort_order
- **ADDED** `buyer_quotes` - Consolidated quotes with versioning (v1, v2, v3)
- **ADDED** `buyer_quote_items` - Items with margin calculation, item-level tax, sort_order
- **ADDED** `buyer_quote_extensions` - Validity extensions with reason logging

### Phase 5: Orders & Fulfillment (6 new tables)
- **ADDED** `buyer_orders` - One consolidated order per Request
- **ADDED** `buyer_order_items` - Locked pricing with item-level tax, sort_order
- **ADDED** `supplier_orders` - Multiple per Request (one per supplier)
- **ADDED** `supplier_order_items` - Locked pricing with item-level tax, sort_order
- **ADDED** `shipments` - Inbound (supplier_order_id) / Outbound (buyer_order_id) with tracking
- **ADDED** `shipment_items` - Ordered vs received, supports both inbound and outbound

### Phase 6: Finance (7 new tables)
- **ADDED** `buyer_invoices` - Prepayment, balance, credit_note, debit_note types
- **ADDED** `buyer_invoice_items` - Line items with tax, links to order items
- **ADDED** `buyer_payments` - With proof via Media Library
- **ADDED** `supplier_invoices` - Multi-currency with credit note support
- **ADDED** `supplier_invoice_items` - Line items with tax, links to order items
- **ADDED** `supplier_payments` - With proof via Media Library
- **ADDED** `request_activities` - Audit trail for all request actions

### CRM Integration (Zero Breaking Changes)
- **PRESERVED** Existing `companies`, `people`, `opportunities` untouched
- **LINKED** Buyers/Suppliers can optionally link to Companies via FK
- **EXTENDED** Tasks/Notes available on ERP entities via polymorphic
- **EXTENDED** Custom fields available on Buyer, Supplier, Article, Request
- **EXTENDED** Media Library used for payment proofs, shipping docs, PODs

## Impact

### New Tables Created: 30

```
Foundation:        tags, taggables, currencies, exchange_rates, tax_codes (5)
Master Data:       buyers, suppliers, articles, supplier_articles (4)
Requests:          projects, requests, request_items (3)
Supplier Quoting:  supplier_quotes, supplier_quote_items (2)
Buyer Quoting:     buyer_quotes, buyer_quote_items, buyer_quote_extensions (3)
Supplier Orders:   supplier_orders, supplier_order_items (2)
Buyer Orders:      buyer_orders, buyer_order_items (2)
Finance:           buyer_invoices, buyer_invoice_items, buyer_payments,
                   supplier_invoices, supplier_invoice_items, supplier_payments (6)
Shipments:         shipments, shipment_items (2)
Activity:          request_activities (1)
```

### Tables NOT Created (Reusing Existing)

| Originally Proposed | Using Instead |
|--------------------|---------------|
| `attachments` | Spatie Media Library (`media` table) |
| `settings` | Spatie Settings (class-based) |
| `roles` | `spatie/laravel-permission` |
| `permissions` | `spatie/laravel-permission` |
| `role_permissions` | `spatie/laravel-permission` |
| `user_roles` | `spatie/laravel-permission` |
| `audit_logs` | `spatie/laravel-activitylog` (`activity_log` table) |

### Packages to Install

```bash
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
```

### Affected Existing Code

| Area | Change |
|------|--------|
| `app/Models/` | 30+ new models |
| `app/Filament/Resources/` | 16+ new Filament resources |
| `app/Observers/` | 14+ new observers for ERP entities |
| `app/Policies/` | New policies using Spatie Permission |
| `app/Services/` | TaxCalculationService for item-level tax |
| `database/migrations/` | 30 new migrations |
| `app/Enums/` | New enums for stages, statuses, InvoiceType |
| `config/permission.php` | Spatie Permission config |
| `AppServiceProvider` | Add ERP entities to morphMap |

### Breaking Changes

**NONE** - This is fully additive:
- Existing CRM features remain fully functional
- No changes to existing tables
- No changes to existing models
- Optional linking only (company_id nullable FK)

## Key Design Decisions

1. **Request as Atomic Unit**: Each Request = complete buyer inquiry lifecycle
2. **Separate Buyers/Suppliers WITH Optional Company Link**: Clean separation, optional CRM integration
3. **Reuse Spatie Ecosystem**: Media Library, Settings, Permission packages
4. **Single Base Currency**: Sales in base currency; purchases multi-currency with conversion
5. **Supplier Confidentiality**: Buyer never sees supplier information
6. **Quote Extensions**: Extend validity with reason logging instead of cancellation
7. **Manual Journaling**: No payment gateway integrations; proof uploads via Media Library
8. **Value Locking**: Prices editable on quotes, locked when converted to orders
9. **Item-Level Tax Handling**: Each line item has tax_code dropdown + inc/exc tax checkbox (ERP best practice)
10. **Outbound Shipment Support**: Shipments track both inbound (from supplier) and outbound (to buyer)
11. **Invoice Items**: Invoices have line items matching quote/order structure for traceability
12. **Credit Notes as Invoice Type**: Credit notes are invoices with `type='credit_note'` and negative amounts
13. **Header-Level Tax Default**: Quotes/orders have `default_tax_code_id` for convenience (items can override)
14. **Two-Tier Activity Logging**: `request_activities` for user-friendly request timeline + Spatie Activity Log for system-wide audit trail

## Success Criteria

1. Complete request-to-payment lifecycle management
2. Real-time per-request profitability calculation
3. Multi-supplier sourcing with consolidated buyer quotes
4. Full audit trail via request_activities (request timeline) AND Spatie Activity Log (system-wide)
5. Credit limit enforcement for buyers
6. Multi-currency support with exchange rate tracking
7. Flexible tax handling (item-level, inc/exc toggle, multiple tax codes)
8. Zero breaking changes to existing CRM functionality
9. Compliance-ready audit logging with field-level change tracking
