# Tasks: Add ERP Trading System

## Phase 0: Prerequisites

### 0.1 Install Spatie Permission
- [x] 0.1.1 Run `composer require spatie/laravel-permission`
- [x] 0.1.2 Publish config: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- [x] 0.1.3 Run migrations: `php artisan migrate`
- [x] 0.1.4 Add `HasRoles` trait to User model
- [x] 0.1.5 Create ERP permissions seeder
- [x] 0.1.6 Create default roles (superadmin, admin, sales, finance, viewer)
- [x] 0.1.7 Write Pest tests for permission checks

### 0.2 Create ErpSettings Class
- [x] 0.2.1 Create `app/Settings/ErpSettings.php` using Spatie Settings
- [x] 0.2.2 Define settings: default_currency, default_tax_percent, quote_validity_days, etc.
- [x] 0.2.3 Create settings migration
- [x] 0.2.4 Create Filament settings page
- [x] 0.2.5 Write Pest tests for settings

### 0.3 Setup MorphMap for ERP Entities
- [x] 0.3.1 Add ERP entities to `Relation::morphMap()` in AppServiceProvider
- [x] 0.3.2 Include: request, buyer, supplier, buyer_payment, supplier_payment, shipment

### 0.4 Install Spatie Activity Log
- [x] 0.4.1 Run `composer require spatie/laravel-activitylog`
- [x] 0.4.2 Publish config: `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"`
- [x] 0.4.3 Publish migrations: `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"`
- [x] 0.4.4 Run migrations: `php artisan migrate`
- [x] 0.4.5 Configure `config/activitylog.php` (default log name, delete records after days)
- [x] 0.4.6 Write Pest tests for activity log functionality

### Phase 0 Checkpoint: Prerequisites Validation
- [ ] 0.5.1 **DB Check:** Verify Spatie tables exist
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt *permission*"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt *activity*"
  ```
- [ ] 0.5.2 **DB Check:** Verify settings migration ran
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "SELECT * FROM settings;"
  ```
- [ ] 0.5.3 **Browser Check:** Login and verify ERP Settings page accessible
- [ ] 0.5.4 **UI Check:** Verify ERP Settings page follows `.claude/skills/ui-components/SKILL.md`:
  - Uses `Section::make()` for form grouping
  - Inputs use proper Filament form components (`TextInput`, `Select`, `Toggle`)
  - Submit button uses `<x-filament::button>` or action pattern
- [ ] 0.5.5 **Test Check:** Run prerequisite tests
  ```bash
  composer test --filter=Permission
  composer test --filter=Settings
  composer test --filter=ActivityLog
  ```
- [ ] 0.5.6 **Sign-off:** Phase 0 complete, ready for Phase 1

---

## Phase 1: Foundation

### 1.1 Tags/Categories System
- [x] 1.1.1 Create `tags` table migration
- [x] 1.1.2 Create `taggables` polymorphic pivot table migration
- [x] 1.1.3 Create `Tag` model with `HasTeam` trait
- [x] 1.1.4 Create `TagObserver` for team_id assignment
- [x] 1.1.5 Create `TagResource` Filament resource
- [x] 1.1.6 Create `HasTags` trait for taggable models
- [x] 1.1.7 Write Pest tests for tags functionality

### 1.2 Currency & Exchange Rates
- [x] 1.2.1 Create `currencies` table migration
- [x] 1.2.2 Create `exchange_rates` table migration
- [x] 1.2.3 Create `Currency` model
- [x] 1.2.4 Create `ExchangeRate` model with team scoping
- [x] 1.2.5 Create `CurrencyResource` Filament resource
- [x] 1.2.6 Create `ExchangeRateResource` Filament resource
- [x] 1.2.7 Create `CurrencyService` for conversion calculations
- [x] 1.2.8 Seed default currencies (USD, IDR, EUR, SGD, CNY)
- [x] 1.2.9 Write Pest tests for currency conversion

### 1.3 Tax Codes
- [x] 1.3.1 Create `tax_codes` table migration (team_id, code, name, rate, is_inclusive_default, is_active, is_default, sort_order)
- [x] 1.3.2 Create `TaxCode` model with `HasTeam` trait
- [x] 1.3.3 Create `TaxCodeObserver` for team_id assignment
- [x] 1.3.4 Create `TaxCodeResource` Filament resource
- [x] 1.3.5 Seed default tax codes (PPN 11%, PPN 0%, Tax Exempt, No Tax)
- [x] 1.3.6 Create `TaxCalculationService` for inc/exc tax calculations
- [x] 1.3.7 Write Pest tests for tax calculation logic

### 1.4 Buyers Entity
- [x] 1.4.1 Create `buyers` table migration (with optional `company_id` FK)
- [x] 1.4.2 Create `Buyer` model with `HasTeam`, `HasCreator`, `UsesCustomFields` traits
- [x] 1.4.3 Create `BuyerObserver` for auto-assignment and code generation
- [x] 1.4.4 Create `BuyerPolicy` using Spatie Permission
- [x] 1.4.5 Create `BuyerResource` Filament resource with form/table
- [x] 1.4.6 Add credit limit and on-hold functionality
- [x] 1.4.7 Add `available_credit` computed accessor
- [x] 1.4.8 Add optional Company linking relationship
- [x] 1.4.9 Write Pest tests for buyer functionality

### 1.5 Suppliers Entity
- [x] 1.5.1 Create `suppliers` table migration (with optional `company_id` FK)
- [x] 1.5.2 Create `Supplier` model with `HasTeam`, `HasCreator`, `HasTags`, `UsesCustomFields` traits
- [x] 1.5.3 Create `SupplierObserver` for auto-assignment and code generation
- [x] 1.5.4 Create `SupplierPolicy` using Spatie Permission
- [x] 1.5.5 Create `SupplierResource` Filament resource with form/table
- [x] 1.5.6 Add supplier-tags relationship
- [x] 1.5.7 Add optional Company linking relationship
- [x] 1.5.8 Write Pest tests for supplier functionality

### 1.6 Articles Entity
- [x] 1.6.1 Create `articles` table migration with JSONB attributes and `default_tax_code_id`
- [x] 1.6.2 Create `supplier_articles` pivot table migration
- [x] 1.6.3 Create `Article` model with `HasTeam`, `HasTags`, `UsesCustomFields` traits
- [x] 1.6.4 Create `ArticleObserver` for auto-assignment
- [x] 1.6.5 Create `ArticlePolicy` using Spatie Permission
- [x] 1.6.6 Create `ArticleResource` Filament resource with dynamic attributes and tax code selector
- [x] 1.6.7 Add supplier-article relationship with last_quoted tracking
- [x] 1.6.8 Write Pest tests for article functionality

### Phase 1 Checkpoint: Foundation Validation
- [ ] 1.7.1 **DB Check:** Verify all Phase 1 tables exist
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt tags"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt taggables"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt currencies"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt exchange_rates"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt tax_codes"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyers"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt suppliers"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt articles"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_articles"
  ```
- [ ] 1.7.2 **DB Check:** Verify seeded data exists
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "SELECT code, name FROM currencies;"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "SELECT code, name, rate FROM tax_codes;"
  ```
- [ ] 1.7.3 **Browser Check:** Tags - List, Create, Edit, Delete
- [ ] 1.7.4 **Browser Check:** Currencies - List, View seeded data
- [ ] 1.7.5 **Browser Check:** Exchange Rates - List, Create with rate
- [ ] 1.7.6 **Browser Check:** Tax Codes - List, View seeded data
- [ ] 1.7.7 **Browser Check:** Buyers - List, Create with credit limit, Edit, verify code auto-generated
- [ ] 1.7.8 **Browser Check:** Suppliers - List, Create with tags, Edit
- [ ] 1.7.9 **Browser Check:** Articles - List, Create with JSONB attributes, assign to supplier
- [ ] 1.7.10 **UI Check:** Verify all resources use Filament components per `.claude/skills/ui-components/SKILL.md`:
  - Forms use `Section::make()` for grouping
  - Tables use proper `TextColumn`, `IconColumn`, `Badge` styling
  - No raw Tailwind where Filament components exist
- [ ] 1.7.11 **Test Check:** Run Phase 1 tests
  ```bash
  composer test --filter=Tag
  composer test --filter=Currency
  composer test --filter=TaxCode
  composer test --filter=Buyer
  composer test --filter=Supplier
  composer test --filter=Article
  ```
- [ ] 1.7.12 **Sign-off:** Phase 1 complete, ready for Phase 2

---

## Phase 2: Request Management

### 2.1 Projects Entity
- [x] 2.1.1 Create `projects` table migration
- [x] 2.1.2 Create `Project` model with `HasTeam`, `HasCreator` traits
- [x] 2.1.3 Create `ProjectObserver` for auto-assignment and numbering (PRJ-YYYY-NNNN)
- [x] 2.1.4 Create `ProjectPolicy` using Spatie Permission
- [x] 2.1.5 Create `ProjectResource` Filament resource
- [x] 2.1.6 Write Pest tests for project functionality

### 2.2 Requests Entity
- [x] 2.2.1 Create `requests` table migration
- [x] 2.2.2 Create `Request` model with `HasTeam`, `HasCreator`, `UsesCustomFields`, `HasMedia` traits
- [x] 2.2.3 Create `RequestObserver` for auto-assignment and numbering (REQ-YYYY-NNNN)
- [x] 2.2.4 Create `RequestPolicy` using Spatie Permission
- [x] 2.2.5 Create `RequestResource` Filament resource with tabbed detail view
- [x] 2.2.6 Create `RequestStage` enum with all stages
- [x] 2.2.7 Add stage transition validation logic
- [x] 2.2.8 Add polymorphic tasks/notes relationships
- [x] 2.2.9 Write Pest tests for request lifecycle

### 2.3 Request Items
- [x] 2.3.1 Create `request_items` table migration with `sort_order` field
- [x] 2.3.2 Create `RequestItem` model
- [x] 2.3.3 Create `RequestItemRelationManager` for Request resource with drag-to-reorder
- [x] 2.3.4 Add vague capture workflow (description → article match)
- [x] 2.3.5 Add match validation (article_id required before supplier quoting)
- [x] 2.3.6 Write Pest tests for request items

### Phase 2 Checkpoint: Request Management Validation
- [ ] 2.4.1 **DB Check:** Verify all Phase 2 tables exist
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt projects"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt requests"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt request_items"
  ```
- [ ] 2.4.2 **DB Check:** Verify table columns
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d requests"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d request_items"
  ```
- [ ] 2.4.3 **Browser Check:** Projects - List, Create, verify PRJ-YYYY-NNNN numbering
- [ ] 2.4.4 **Browser Check:** Requests - List, Create with buyer selection, verify REQ-YYYY-NNNN numbering
- [ ] 2.4.5 **Browser Check:** Request Items - Add items to request, verify sort_order drag-reorder
- [ ] 2.4.6 **Browser Check:** Request Items - Test vague description → article match workflow
- [ ] 2.4.7 **Browser Check:** Request Stage - Verify stage transitions work
- [ ] 2.4.8 **UI Check:** Verify Request resource follows `.claude/skills/ui-components/SKILL.md`:
  - Tabbed detail view uses Filament tabs component
  - RelationManagers use proper Filament patterns
  - Stage badges use `->badge()` with color mapping
  - Drag-reorder uses Filament's sortable component
- [ ] 2.4.9 **Test Check:** Run Phase 2 tests
  ```bash
  composer test --filter=Project
  composer test --filter=Request
  composer test --filter=RequestItem
  ```
- [ ] 2.4.10 **Sign-off:** Phase 2 complete, ready for Phase 3

---

## Phase 3: Multi-Supplier Quoting

### 3.1 Supplier Quotes
- [x] 3.1.1 Create `supplier_quotes` table migration (subtotal, tax_total, total)
- [x] 3.1.2 Create `supplier_quote_items` table migration with item-level tax fields (tax_code_id, is_tax_inclusive, tax_rate, unit_price_exc_tax, tax_amount, sort_order)
- [x] 3.1.3 Create `SupplierQuote` model with exchange rate tracking
- [x] 3.1.4 Create `SupplierQuoteItem` model with request_item traceability and tax calculation
- [x] 3.1.5 Create `SupplierQuoteObserver` with header totals recalculation
- [x] 3.1.6 Create `SupplierQuoteStatus` enum (pending, selected, rejected, expired)
- [x] 3.1.7 Create `SupplierQuotesRelationManager` for Request resource
- [x] 3.1.8 Add tax code dropdown and inc/exc tax checkbox to line item form
- [x] 3.1.9 Add currency conversion display (original + base)
- [x] 3.1.10 Add consolidated cost summary calculation
- [x] 3.1.11 Write Pest tests for supplier quotes with tax calculations

### 3.2 Buyer Quotes
- [x] 3.2.1 Create `buyer_quotes` table migration with versioning (subtotal, tax_total, total)
- [x] 3.2.2 Create `buyer_quote_items` table migration with margin fields and item-level tax (tax_code_id, is_tax_inclusive, tax_rate, sort_order)
- [x] 3.2.3 Create `buyer_quote_extensions` table migration
- [x] 3.2.4 Create `BuyerQuote` model with versioning support
- [x] 3.2.5 Create `BuyerQuoteItem` model with margin calculation and tax handling
- [x] 3.2.6 Create `BuyerQuoteExtension` model
- [x] 3.2.7 Create `BuyerQuoteObserver` with header totals recalculation
- [x] 3.2.8 Create `BuyerQuoteStatus` enum (draft, sent, accepted, rejected, expired, superseded)
- [x] 3.2.9 Create `BuyerQuotesRelationManager` for Request resource
- [x] 3.2.10 Add quote consolidation from multiple supplier quotes
- [x] 3.2.11 Add quote versioning (v1, v2, v3...)
- [x] 3.2.12 Add quote extension modal with reason logging
- [x] 3.2.13 Add tax code dropdown and inc/exc tax checkbox to line item form
- [x] 3.2.14 Add internal vs buyer view (supplier info hidden from buyer PDF)
- [x] 3.2.15 Write Pest tests for buyer quotes with tax calculations

### Phase 3 Checkpoint: Multi-Supplier Quoting Validation
- [ ] 3.3.1 **DB Check:** Verify all Phase 3 tables exist
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_quotes"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_quote_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_quotes"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_quote_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_quote_extensions"
  ```
- [ ] 3.3.2 **DB Check:** Verify item-level tax columns exist
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d supplier_quote_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d buyer_quote_items"
  ```
- [ ] 3.3.3 **Browser Check:** Supplier Quote - Create from Request, add items with tax code selection
- [ ] 3.3.4 **Browser Check:** Supplier Quote - Verify tax inc/exc toggle calculates correctly
- [ ] 3.3.5 **Browser Check:** Supplier Quote - Verify currency conversion display
- [ ] 3.3.6 **Browser Check:** Supplier Quote - Select/Reject quotes
- [ ] 3.3.7 **Browser Check:** Buyer Quote - Create from selected supplier quotes
- [ ] 3.3.8 **Browser Check:** Buyer Quote - Verify margin calculation
- [ ] 3.3.9 **Browser Check:** Buyer Quote - Test versioning (create v2, v3)
- [ ] 3.3.10 **Browser Check:** Buyer Quote - Test extension with reason
- [ ] 3.3.11 **UI Check:** Verify Quote resources follow `.claude/skills/ui-components/SKILL.md`:
  - Line item forms use Filament Repeater with proper field composition
  - Tax code dropdown uses `Select::make()->options()` or `->relationship()`
  - Currency conversion display uses proper number formatting
  - Status badges use `->badge()` with semantic colors (success/warning/danger)
  - Quote comparison uses Filament `<x-filament::section>` for grouping
- [ ] 3.3.12 **Test Check:** Run Phase 3 tests
  ```bash
  composer test --filter=SupplierQuote
  composer test --filter=BuyerQuote
  composer test --filter=TaxCalculation
  ```
- [ ] 3.3.13 **Sign-off:** Phase 3 complete, ready for Phase 4

---

## Phase 4: Orders & Fulfillment

### 4.1 Buyer Orders
- [x] 4.1.1 Create `buyer_orders` table migration with locked payment terms (subtotal, tax_total, total)
- [x] 4.1.2 Create `buyer_order_items` table migration with locked tax fields (tax_code_id, is_tax_inclusive, tax_rate, sort_order)
- [x] 4.1.3 Create `BuyerOrder` model with value locking
- [x] 4.1.4 Create `BuyerOrderItem` model with locked tax info
- [x] 4.1.5 Create `BuyerOrderObserver`
- [x] 4.1.6 Create `OrderStatus` enum
- [x] 4.1.7 Create `BuyerOrdersRelationManager` for Request resource
- [x] 4.1.8 Add create-from-accepted-quote workflow (copy tax fields from quote items)
- [x] 4.1.9 Add payment terms locking (copied from quote)
- [x] 4.1.10 Add auto-generated order_number (BO-YYYY-NNNN)
- [x] 4.1.11 Add credit limit check with warning
- [x] 4.1.12 Write Pest tests for buyer orders

### 4.2 Supplier Orders
- [x] 4.2.1 Create `supplier_orders` table migration with exchange rate snapshot (subtotal, tax_total, total)
- [x] 4.2.2 Create `supplier_order_items` table migration with locked tax fields (tax_code_id, is_tax_inclusive, tax_rate, sort_order)
- [x] 4.2.3 Create `SupplierOrder` model with value locking
- [x] 4.2.4 Create `SupplierOrderItem` model with locked tax info
- [x] 4.2.5 Create `SupplierOrderObserver`
- [x] 4.2.6 Create `SupplierOrdersRelationManager` for Request resource
- [x] 4.2.7 Add auto-generation from selected supplier quotes (copy tax fields)
- [x] 4.2.8 Add auto-generated po_number (PO-YYYY-NNNN-A/B/C)
- [x] 4.2.9 Write Pest tests for supplier orders

### 4.3 Shipments
- [x] 4.3.1 Create `shipments` table migration with `supplier_order_id` (inbound) and `buyer_order_id` (outbound)
- [x] 4.3.2 Create `shipment_items` table migration with `supplier_order_item_id` OR `buyer_order_item_id`, sort_order
- [x] 4.3.3 Create `Shipment` model with `HasMedia` trait for POD uploads
- [x] 4.3.4 Create `ShipmentItem` model for quantity verification (supports both inbound and outbound)
- [x] 4.3.5 Create `ShipmentObserver`
- [x] 4.3.6 Create `ShipmentType` enum (inbound, outbound)
- [x] 4.3.7 Create `ShipmentStatus` enum (pending, in_transit, delivered, partial, failed)
- [x] 4.3.8 Create `ItemCondition` enum (good, damaged, rejected)
- [x] 4.3.9 Create `ShipmentsRelationManager` for Request resource
- [x] 4.3.10 Add ordered vs received comparison
- [x] 4.3.11 Register media collections (shipping_doc, pod)
- [x] 4.3.12 Write Pest tests for shipments

### Phase 4 Checkpoint: Orders & Fulfillment Validation
- [ ] 4.4.1 **DB Check:** Verify all Phase 4 tables exist
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_orders"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_order_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_orders"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_order_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt shipments"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt shipment_items"
  ```
- [ ] 4.4.2 **DB Check:** Verify order columns have locked tax fields
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d buyer_order_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d supplier_order_items"
  ```
- [ ] 4.4.3 **Browser Check:** Buyer Order - Create from accepted buyer quote
- [ ] 4.4.4 **Browser Check:** Buyer Order - Verify BO-YYYY-NNNN numbering
- [ ] 4.4.5 **Browser Check:** Buyer Order - Verify tax fields locked (not editable)
- [ ] 4.4.6 **Browser Check:** Buyer Order - Verify credit limit warning shows
- [ ] 4.4.7 **Browser Check:** Supplier Order - Auto-generated from selected supplier quotes
- [ ] 4.4.8 **Browser Check:** Supplier Order - Verify PO-YYYY-NNNN-A/B/C numbering
- [ ] 4.4.9 **Browser Check:** Shipment (Inbound) - Create from supplier order, record received qty
- [ ] 4.4.10 **Browser Check:** Shipment (Outbound) - Create from buyer order, record shipped qty
- [ ] 4.4.11 **Browser Check:** Shipment - Upload POD document
- [ ] 4.4.12 **Browser Check:** Shipment - Verify ordered vs received comparison
- [ ] 4.4.13 **UI Check:** Verify Order/Shipment resources follow `.claude/skills/ui-components/SKILL.md`:
  - Locked fields displayed as disabled inputs or read-only text
  - Credit limit warning uses Filament notification or banner component
  - Order status uses `->badge()` with workflow-appropriate colors
  - Shipment quantity comparison uses clear visual diff (green/red indicators)
  - POD upload uses Filament `FileUpload` with proper media collection
- [ ] 4.4.14 **Test Check:** Run Phase 4 tests
  ```bash
  composer test --filter=BuyerOrder
  composer test --filter=SupplierOrder
  composer test --filter=Shipment
  ```
- [ ] 4.4.15 **Sign-off:** Phase 4 complete, ready for Phase 5

---

## Phase 5: Finance & Journaling

### 5.1 Buyer Invoices & Payments
- [x] 5.1.1 Create `buyer_invoices` table migration (with original_invoice_id, credit_reason)
- [x] 5.1.2 Create `buyer_invoice_items` table migration (with tax fields, sort_order)
- [x] 5.1.3 Create `buyer_payments` table migration
- [x] 5.1.4 Create `BuyerInvoice` model with prepayment/balance/credit_note types
- [x] 5.1.5 Create `BuyerInvoiceItem` model with tax calculation
- [x] 5.1.6 Create `BuyerPayment` model with `HasMedia` trait for proof uploads
- [x] 5.1.7 Create `BuyerInvoiceObserver` for totals calculation
- [x] 5.1.8 Create `InvoiceType` enum (prepayment, balance, standard, credit_note, debit_note)
- [x] 5.1.9 Create `InvoiceStatus` enum (draft, sent, partial, paid, overdue, cancelled)
- [x] 5.1.10 Create `PaymentMethod` enum (bank_transfer, cash, check, lc, other)
- [x] 5.1.11 Create `BuyerInvoicesRelationManager` for Request resource
- [x] 5.1.12 Create `BuyerInvoiceItemsRelationManager` for BuyerInvoice resource
- [x] 5.1.13 Add due date calculation (prepayment=issued_at, balance=delivery+net_days)
- [x] 5.1.14 Add payment recording with required proof upload via Media Library
- [x] 5.1.15 Add `amount_paid`, `amount_outstanding`, `days_overdue` accessors
- [x] 5.1.16 Implement credit note creation from original invoice
- [x] 5.1.17 Register media collection (payment_proof)
- [x] 5.1.18 Write Pest tests for buyer invoices/payments/credit notes

### 5.2 Supplier Invoices & Payments
- [x] 5.2.1 Create `supplier_invoices` table migration (with original_invoice_id, credit_reason)
- [x] 5.2.2 Create `supplier_invoice_items` table migration (with tax fields, sort_order)
- [x] 5.2.3 Create `supplier_payments` table migration
- [x] 5.2.4 Create `SupplierInvoice` model with multi-currency and credit_note type
- [x] 5.2.5 Create `SupplierInvoiceItem` model with tax calculation
- [x] 5.2.6 Create `SupplierPayment` model with `HasMedia` trait for proof uploads
- [x] 5.2.7 Create `SupplierInvoiceObserver` for totals calculation
- [x] 5.2.8 Create `SupplierInvoicesRelationManager` for Request resource
- [x] 5.2.9 Create `SupplierInvoiceItemsRelationManager` for SupplierInvoice resource
- [x] 5.2.10 Add exchange rate snapshot at invoice time
- [x] 5.2.11 Add payment recording with required proof upload via Media Library
- [x] 5.2.12 Implement credit note creation from original invoice
- [x] 5.2.13 Register media collection (payment_proof)
- [x] 5.2.14 Write Pest tests for supplier invoices/payments/credit notes

### 5.3 Activity & Audit Logging

#### 5.3.1 Request Activity Timeline (User-Facing)
- [x] 5.3.1.1 Create `request_activities` table migration
- [x] 5.3.1.2 Create `RequestActivity` model
- [x] 5.3.1.3 Create `ActivityType` enum for all activity types
- [x] 5.3.1.4 Add activity logging to all request-related observers
- [x] 5.3.1.5 Create Activity Log tab in Request resource
- [x] 5.3.1.6 Write Pest tests for request activity logging

#### 5.3.2 System Audit Log (Spatie Activity Log)
- [x] 5.3.2.1 Add `LogsActivity` trait to all ERP models
- [x] 5.3.2.2 Configure `getActivitylogOptions()` for each model (logged fields)
- [x] 5.3.2.3 Create auth event listeners for login/logout tracking with IP and user agent
- [x] 5.3.2.4 Create `AuditLogResource` Filament resource (admin only)
- [x] 5.3.2.5 Add filters: date range, user, entity type, action type
- [x] 5.3.2.6 Add search: entity name, user name, description
- [x] 5.3.2.7 Create Audit Detail modal with old/new values side-by-side
- [x] 5.3.2.8 Add CSV export action for compliance reports
- [x] 5.3.2.9 Write Pest tests for system audit log

### 5.4 P&L Calculation
- [x] 5.4.1 Add `buyer_total` accessor to Request model
- [x] 5.4.2 Add `supplier_cost` accessor (sum of supplier orders in base currency)
- [x] 5.4.3 Add `gross_margin` accessor (buyer_total - supplier_cost)
- [x] 5.4.4 Add `margin_percent` accessor
- [x] 5.4.5 Add `amount_collected`, `amount_paid_out`, `net_cash_flow` accessors
- [x] 5.4.6 Add Financial Summary widget to Request detail page
- [x] 5.4.7 Write Pest tests for P&L calculations

### Phase 5 Checkpoint: Finance & Journaling Validation
- [ ] 5.5.1 **DB Check:** Verify all Phase 5 tables exist
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_invoices"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_invoice_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt buyer_payments"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_invoices"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_invoice_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt supplier_payments"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\dt request_activities"
  ```
- [ ] 5.5.2 **DB Check:** Verify invoice item columns
  ```bash
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d buyer_invoice_items"
  docker compose exec pgsql psql -U relaticle -d relaticle -c "\d supplier_invoice_items"
  ```
- [ ] 5.5.3 **Browser Check:** Buyer Invoice - Create prepayment invoice from buyer order
- [ ] 5.5.4 **Browser Check:** Buyer Invoice - Create balance invoice after delivery
- [ ] 5.5.5 **Browser Check:** Buyer Invoice - Verify due date calculation
- [ ] 5.5.6 **Browser Check:** Buyer Payment - Record payment with proof upload
- [ ] 5.5.7 **Browser Check:** Buyer Invoice - Verify amount_outstanding updates
- [ ] 5.5.8 **Browser Check:** Buyer Invoice - Create credit note from original invoice
- [ ] 5.5.9 **Browser Check:** Supplier Invoice - Create from supplier order
- [ ] 5.5.10 **Browser Check:** Supplier Invoice - Verify exchange rate snapshot
- [ ] 5.5.11 **Browser Check:** Supplier Payment - Record payment with proof upload
- [ ] 5.5.12 **Browser Check:** Request Activity - View activity timeline tab
- [ ] 5.5.13 **Browser Check:** Audit Log - View system audit log (admin only)
- [ ] 5.5.14 **Browser Check:** Audit Log - Filter by date, user, entity type
- [ ] 5.5.15 **Browser Check:** Audit Log - Export CSV
- [ ] 5.5.16 **Browser Check:** Request Detail - Verify Financial Summary widget shows P&L
- [ ] 5.5.17 **UI Check:** Verify Finance resources follow `.claude/skills/ui-components/SKILL.md`:
  - Invoice/Payment forms use `Section::make()` for logical grouping
  - Payment proof upload uses Filament `FileUpload` component
  - Invoice status badges use appropriate colors (draft=gray, sent=info, overdue=danger, paid=success)
  - Activity timeline uses Filament timeline or list component
  - Audit log filters use Filament's built-in `SelectFilter`, `Filter` components
  - P&L widget uses `<x-filament::section>` with proper stats formatting
  - Credit note creation uses `<x-filament::modal>` for workflow
- [ ] 5.5.18 **Test Check:** Run Phase 5 tests
  ```bash
  composer test --filter=BuyerInvoice
  composer test --filter=BuyerPayment
  composer test --filter=SupplierInvoice
  composer test --filter=SupplierPayment
  composer test --filter=RequestActivity
  composer test --filter=AuditLog
  composer test --filter=ProfitLoss
  ```
- [ ] 5.5.19 **Sign-off:** Phase 5 complete, ready for Phase 6

---

## Phase 6: Dashboard & Polish

### 6.1 Dashboard
- [x] 6.1.1 Create ERP Dashboard page or widgets
- [x] 6.1.2 Add Active Requests KPI widget
- [x] 6.1.3 Add Quotes Expiring widget with alert styling
- [x] 6.1.4 Add Awaiting Payment widget
- [x] 6.1.5 Add Monthly Revenue widget
- [x] 6.1.6 Add Pipeline by Stage chart
- [x] 6.1.7 Add Requires Attention list widget
- [x] 6.1.8 Write Pest tests for dashboard widgets

### 6.2 Alerts & Notifications
- [x] 6.2.1 Add quote expiration alerts (7 days, 3 days, 1 day)
- [x] 6.2.2 Add payment overdue alerts
- [x] 6.2.3 Add awaiting supplier quotes alerts
- [x] 6.2.4 Add credit limit warning on order creation

### 6.3 PDF Generation
- [x] 6.3.1 Create Buyer Quote PDF template (no supplier info)
- [x] 6.3.2 Create Buyer Order confirmation PDF
- [x] 6.3.3 Create Buyer Invoice PDF
- [x] 6.3.4 Create Supplier Order (PO) PDF
- [x] 6.3.5 Add download/email actions to Filament resources

### 6.4 Final Testing & Cleanup
- [x] 6.4.1 Run full test suite and fix failures
- [x] 6.4.2 Run PHPStan and fix type errors
- [x] 6.4.3 Run Rector and Pint for code style
- [x] 6.4.4 Add architecture tests for new ERP models
- [ ] 6.4.5 Verify 80% code coverage
- [ ] 6.4.6 Update README and documentation

### Phase 6 Checkpoint: Dashboard & Polish Validation
- [ ] 6.5.1 **Browser Check:** Dashboard - View all widgets with data
- [ ] 6.5.2 **Browser Check:** Dashboard - Verify KPI numbers match database
- [ ] 6.5.3 **Browser Check:** Alerts - Verify quote expiration alerts show
- [ ] 6.5.4 **Browser Check:** Alerts - Verify payment overdue alerts show
- [ ] 6.5.5 **Browser Check:** PDF - Download Buyer Quote PDF, verify no supplier info
- [ ] 6.5.6 **Browser Check:** PDF - Download Buyer Order PDF
- [ ] 6.5.7 **Browser Check:** PDF - Download Buyer Invoice PDF
- [ ] 6.5.8 **Browser Check:** PDF - Download Supplier Order (PO) PDF
- [ ] 6.5.9 **UI Check:** Verify Dashboard follows `.claude/skills/ui-components/SKILL.md`:
  - Dashboard uses Filament Widgets with proper `Stat`, `Chart` components
  - KPI widgets use `<x-filament-widgets::stats-overview-widget.stat>`
  - Alert styling uses Filament's notification/banner patterns
  - Requires Attention list uses Filament table patterns
  - All widgets support dark mode
  - No raw Tailwind where Filament dashboard components exist
- [ ] 6.5.10 **UI Check:** Verify PDF templates follow project patterns:
  - Use consistent typography and spacing
  - Follow brand color guidelines
  - Print-friendly layout (no dark mode)
- [ ] 6.5.11 **Test Check:** Run full test suite
  ```bash
  composer test
  composer test:arch
  composer test:types
  ```
- [ ] 6.5.12 **Lint Check:** Run code quality checks
  ```bash
  composer lint
  ```
- [ ] 6.5.13 **Coverage Check:** Verify 80% coverage
  ```bash
  composer test -- --coverage --min=80
  ```
- [ ] 6.5.14 **Sign-off:** Phase 6 complete, ERP Trading System ready for deployment

---

## End-to-End Workflow Test

After all phases complete, perform a full workflow test:

- [ ] E2E.1 **Create Test Data:**
  - Create a Buyer with credit limit
  - Create 2 Suppliers with tags
  - Create 3 Articles linked to suppliers

- [ ] E2E.2 **Request Lifecycle:**
  - Create a new Request for the Buyer
  - Add 3 Request Items (vague descriptions)
  - Match items to Articles

- [ ] E2E.3 **Quoting:**
  - Create Supplier Quotes from both suppliers (different currencies)
  - Select best quotes from each supplier
  - Create consolidated Buyer Quote
  - Send to buyer, create v2 with discount
  - Buyer accepts quote

- [ ] E2E.4 **Orders:**
  - Convert accepted quote to Buyer Order
  - Auto-generate Supplier Orders (verify PO numbers)
  - Verify credit limit warning if applicable

- [ ] E2E.5 **Fulfillment:**
  - Record inbound shipments from suppliers
  - Verify received quantities
  - Record outbound shipment to buyer
  - Upload POD

- [ ] E2E.6 **Finance:**
  - Create prepayment invoice, record payment
  - Create balance invoice after delivery
  - Record final payment
  - Create supplier invoices
  - Record supplier payments
  - Issue credit note for damaged item

- [ ] E2E.7 **Verification:**
  - Verify Request stage = Completed
  - Verify P&L calculation is accurate
  - Verify activity timeline shows all actions
  - Verify audit log has complete history

- [ ] E2E.8 **UI Consistency Check:**
  - Verify all screens follow `.claude/skills/ui-components/SKILL.md` composition hierarchy
  - No raw Tailwind where Filament/Blade components exist
  - All forms use proper Section grouping
  - All status fields use `->badge()` with semantic colors
  - Dark mode works correctly across all views

---

## Task Summary

| Phase | Implementation | Checkpoint | Total |
|-------|----------------|------------|-------|
| 0 | 19 | 6 | 25 |
| 1 | 48 | 12 | 60 |
| 2 | 18 | 10 | 28 |
| 3 | 26 | 13 | 39 |
| 4 | 32 | 15 | 47 |
| 5 | 40 | 19 | 59 |
| 6 | 20 | 14 | 34 |
| E2E | - | 8 | 8 |
| **Total** | **203** | **97** | **300** |
