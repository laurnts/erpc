# Tasks: Add ERP Trading System

## Phase 0: Prerequisites

### 0.1 Install Spatie Permission
- [ ] 0.1.1 Run `composer require spatie/laravel-permission`
- [ ] 0.1.2 Publish config: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- [ ] 0.1.3 Run migrations: `php artisan migrate`
- [ ] 0.1.4 Add `HasRoles` trait to User model
- [ ] 0.1.5 Create ERP permissions seeder
- [ ] 0.1.6 Create default roles (superadmin, admin, sales, finance, viewer)
- [ ] 0.1.7 Write Pest tests for permission checks

### 0.2 Create ErpSettings Class
- [ ] 0.2.1 Create `app/Settings/ErpSettings.php` using Spatie Settings
- [ ] 0.2.2 Define settings: default_currency, default_tax_percent, quote_validity_days, etc.
- [ ] 0.2.3 Create settings migration
- [ ] 0.2.4 Create Filament settings page
- [ ] 0.2.5 Write Pest tests for settings

### 0.3 Setup MorphMap for ERP Entities
- [ ] 0.3.1 Add ERP entities to `Relation::morphMap()` in AppServiceProvider
- [ ] 0.3.2 Include: request, buyer, supplier, buyer_payment, supplier_payment, shipment

### 0.4 Install Spatie Activity Log ⭐ NEW
- [ ] 0.4.1 Run `composer require spatie/laravel-activitylog`
- [ ] 0.4.2 Publish config: `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"`
- [ ] 0.4.3 Publish migrations: `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"`
- [ ] 0.4.4 Run migrations: `php artisan migrate`
- [ ] 0.4.5 Configure `config/activitylog.php` (default log name, delete records after days)
- [ ] 0.4.6 Write Pest tests for activity log functionality

## Phase 1: Foundation

### 1.1 Tags/Categories System
- [ ] 1.1.1 Create `tags` table migration
- [ ] 1.1.2 Create `taggables` polymorphic pivot table migration
- [ ] 1.1.3 Create `Tag` model with `HasTeam` trait
- [ ] 1.1.4 Create `TagObserver` for team_id assignment
- [ ] 1.1.5 Create `TagResource` Filament resource
- [ ] 1.1.6 Create `HasTags` trait for taggable models
- [ ] 1.1.7 Write Pest tests for tags functionality

### 1.2 Currency & Exchange Rates
- [ ] 1.2.1 Create `currencies` table migration
- [ ] 1.2.2 Create `exchange_rates` table migration
- [ ] 1.2.3 Create `Currency` model
- [ ] 1.2.4 Create `ExchangeRate` model with team scoping
- [ ] 1.2.5 Create `CurrencyResource` Filament resource
- [ ] 1.2.6 Create `ExchangeRateResource` Filament resource
- [ ] 1.2.7 Create `CurrencyService` for conversion calculations
- [ ] 1.2.8 Seed default currencies (USD, IDR, EUR, SGD, CNY)
- [ ] 1.2.9 Write Pest tests for currency conversion

### 1.3 Tax Codes ⭐ NEW
- [ ] 1.3.1 Create `tax_codes` table migration (team_id, code, name, rate, is_inclusive_default, is_active, is_default, sort_order)
- [ ] 1.3.2 Create `TaxCode` model with `HasTeam` trait
- [ ] 1.3.3 Create `TaxCodeObserver` for team_id assignment
- [ ] 1.3.4 Create `TaxCodeResource` Filament resource
- [ ] 1.3.5 Seed default tax codes (PPN 11%, PPN 0%, Tax Exempt, No Tax)
- [ ] 1.3.6 Create `TaxCalculationService` for inc/exc tax calculations
- [ ] 1.3.7 Write Pest tests for tax calculation logic

### 1.4 Buyers Entity
- [ ] 1.4.1 Create `buyers` table migration (with optional `company_id` FK)
- [ ] 1.4.2 Create `Buyer` model with `HasTeam`, `HasCreator`, `UsesCustomFields` traits
- [ ] 1.4.3 Create `BuyerObserver` for auto-assignment and code generation
- [ ] 1.4.4 Create `BuyerPolicy` using Spatie Permission
- [ ] 1.4.5 Create `BuyerResource` Filament resource with form/table
- [ ] 1.4.6 Add credit limit and on-hold functionality
- [ ] 1.4.7 Add `available_credit` computed accessor
- [ ] 1.4.8 Add optional Company linking relationship
- [ ] 1.4.9 Write Pest tests for buyer functionality

### 1.5 Suppliers Entity
- [ ] 1.5.1 Create `suppliers` table migration (with optional `company_id` FK)
- [ ] 1.5.2 Create `Supplier` model with `HasTeam`, `HasCreator`, `HasTags`, `UsesCustomFields` traits
- [ ] 1.5.3 Create `SupplierObserver` for auto-assignment and code generation
- [ ] 1.5.4 Create `SupplierPolicy` using Spatie Permission
- [ ] 1.5.5 Create `SupplierResource` Filament resource with form/table
- [ ] 1.5.6 Add supplier-tags relationship
- [ ] 1.5.7 Add optional Company linking relationship
- [ ] 1.5.8 Write Pest tests for supplier functionality

### 1.6 Articles Entity
- [ ] 1.6.1 Create `articles` table migration with JSONB attributes and `default_tax_code_id`
- [ ] 1.6.2 Create `supplier_articles` pivot table migration
- [ ] 1.6.3 Create `Article` model with `HasTeam`, `HasTags`, `UsesCustomFields` traits
- [ ] 1.6.4 Create `ArticleObserver` for auto-assignment
- [ ] 1.6.5 Create `ArticlePolicy` using Spatie Permission
- [ ] 1.6.6 Create `ArticleResource` Filament resource with dynamic attributes and tax code selector
- [ ] 1.6.7 Add supplier-article relationship with last_quoted tracking
- [ ] 1.6.8 Write Pest tests for article functionality

## Phase 2: Request Management

### 2.1 Projects Entity
- [ ] 2.1.1 Create `projects` table migration
- [ ] 2.1.2 Create `Project` model with `HasTeam`, `HasCreator` traits
- [ ] 2.1.3 Create `ProjectObserver` for auto-assignment and numbering (PRJ-YYYY-NNNN)
- [ ] 2.1.4 Create `ProjectPolicy` using Spatie Permission
- [ ] 2.1.5 Create `ProjectResource` Filament resource
- [ ] 2.1.6 Write Pest tests for project functionality

### 2.2 Requests Entity
- [ ] 2.2.1 Create `requests` table migration
- [ ] 2.2.2 Create `Request` model with `HasTeam`, `HasCreator`, `UsesCustomFields`, `HasMedia` traits
- [ ] 2.2.3 Create `RequestObserver` for auto-assignment and numbering (REQ-YYYY-NNNN)
- [ ] 2.2.4 Create `RequestPolicy` using Spatie Permission
- [ ] 2.2.5 Create `RequestResource` Filament resource with tabbed detail view
- [ ] 2.2.6 Create `RequestStage` enum with all stages
- [ ] 2.2.7 Add stage transition validation logic
- [ ] 2.2.8 Add polymorphic tasks/notes relationships
- [ ] 2.2.9 Write Pest tests for request lifecycle

### 2.3 Request Items
- [ ] 2.3.1 Create `request_items` table migration with `sort_order` field
- [ ] 2.3.2 Create `RequestItem` model
- [ ] 2.3.3 Create `RequestItemRelationManager` for Request resource with drag-to-reorder
- [ ] 2.3.4 Add vague capture workflow (description → article match)
- [ ] 2.3.5 Add match validation (article_id required before supplier quoting)
- [ ] 2.3.6 Write Pest tests for request items

## Phase 3: Multi-Supplier Quoting

### 3.1 Supplier Quotes
- [ ] 3.1.1 Create `supplier_quotes` table migration (subtotal, tax_total, total)
- [ ] 3.1.2 Create `supplier_quote_items` table migration with item-level tax fields (tax_code_id, is_tax_inclusive, tax_rate, unit_price_exc_tax, tax_amount, sort_order)
- [ ] 3.1.3 Create `SupplierQuote` model with exchange rate tracking
- [ ] 3.1.4 Create `SupplierQuoteItem` model with request_item traceability and tax calculation
- [ ] 3.1.5 Create `SupplierQuoteObserver` with header totals recalculation
- [ ] 3.1.6 Create `SupplierQuoteStatus` enum (pending, selected, rejected, expired)
- [ ] 3.1.7 Create `SupplierQuotesRelationManager` for Request resource
- [ ] 3.1.8 Add tax code dropdown and inc/exc tax checkbox to line item form
- [ ] 3.1.9 Add currency conversion display (original + base)
- [ ] 3.1.10 Add consolidated cost summary calculation
- [ ] 3.1.11 Write Pest tests for supplier quotes with tax calculations

### 3.2 Buyer Quotes
- [ ] 3.2.1 Create `buyer_quotes` table migration with versioning (subtotal, tax_total, total)
- [ ] 3.2.2 Create `buyer_quote_items` table migration with margin fields and item-level tax (tax_code_id, is_tax_inclusive, tax_rate, sort_order)
- [ ] 3.2.3 Create `buyer_quote_extensions` table migration
- [ ] 3.2.4 Create `BuyerQuote` model with versioning support
- [ ] 3.2.5 Create `BuyerQuoteItem` model with margin calculation and tax handling
- [ ] 3.2.6 Create `BuyerQuoteExtension` model
- [ ] 3.2.7 Create `BuyerQuoteObserver` with header totals recalculation
- [ ] 3.2.8 Create `BuyerQuoteStatus` enum (draft, sent, accepted, rejected, expired, superseded)
- [ ] 3.2.9 Create `BuyerQuotesRelationManager` for Request resource
- [ ] 3.2.10 Add quote consolidation from multiple supplier quotes
- [ ] 3.2.11 Add quote versioning (v1, v2, v3...)
- [ ] 3.2.12 Add quote extension modal with reason logging
- [ ] 3.2.13 Add tax code dropdown and inc/exc tax checkbox to line item form
- [ ] 3.2.14 Add internal vs buyer view (supplier info hidden from buyer PDF)
- [ ] 3.2.15 Write Pest tests for buyer quotes with tax calculations

## Phase 4: Orders & Fulfillment

### 4.1 Buyer Orders
- [ ] 4.1.1 Create `buyer_orders` table migration with locked payment terms (subtotal, tax_total, total)
- [ ] 4.1.2 Create `buyer_order_items` table migration with locked tax fields (tax_code_id, is_tax_inclusive, tax_rate, sort_order)
- [ ] 4.1.3 Create `BuyerOrder` model with value locking
- [ ] 4.1.4 Create `BuyerOrderItem` model with locked tax info
- [ ] 4.1.5 Create `BuyerOrderObserver`
- [ ] 4.1.6 Create `OrderStatus` enum
- [ ] 4.1.7 Create `BuyerOrdersRelationManager` for Request resource
- [ ] 4.1.8 Add create-from-accepted-quote workflow (copy tax fields from quote items)
- [ ] 4.1.9 Add payment terms locking (copied from quote)
- [ ] 4.1.10 Add auto-generated order_number (BO-YYYY-NNNN)
- [ ] 4.1.11 Add credit limit check with warning
- [ ] 4.1.12 Write Pest tests for buyer orders

### 4.2 Supplier Orders
- [ ] 4.2.1 Create `supplier_orders` table migration with exchange rate snapshot (subtotal, tax_total, total)
- [ ] 4.2.2 Create `supplier_order_items` table migration with locked tax fields (tax_code_id, is_tax_inclusive, tax_rate, sort_order)
- [ ] 4.2.3 Create `SupplierOrder` model with value locking
- [ ] 4.2.4 Create `SupplierOrderItem` model with locked tax info
- [ ] 4.2.5 Create `SupplierOrderObserver`
- [ ] 4.2.6 Create `SupplierOrdersRelationManager` for Request resource
- [ ] 4.2.7 Add auto-generation from selected supplier quotes (copy tax fields)
- [ ] 4.2.8 Add auto-generated po_number (PO-YYYY-NNNN-A/B/C)
- [ ] 4.2.9 Write Pest tests for supplier orders

### 4.3 Shipments
- [ ] 4.3.1 Create `shipments` table migration with `supplier_order_id` (inbound) and `buyer_order_id` (outbound)
- [ ] 4.3.2 Create `shipment_items` table migration with `supplier_order_item_id` OR `buyer_order_item_id`, sort_order
- [ ] 4.3.3 Create `Shipment` model with `HasMedia` trait for POD uploads
- [ ] 4.3.4 Create `ShipmentItem` model for quantity verification (supports both inbound and outbound)
- [ ] 4.3.5 Create `ShipmentObserver`
- [ ] 4.3.6 Create `ShipmentType` enum (inbound, outbound)
- [ ] 4.3.7 Create `ShipmentStatus` enum (pending, in_transit, delivered, partial, failed)
- [ ] 4.3.8 Create `ItemCondition` enum (good, damaged, rejected)
- [ ] 4.3.9 Create `ShipmentsRelationManager` for Request resource
- [ ] 4.3.10 Add ordered vs received comparison
- [ ] 4.3.11 Register media collections (shipping_doc, pod)
- [ ] 4.3.12 Write Pest tests for shipments

## Phase 5: Finance & Journaling

### 5.1 Buyer Invoices & Payments
- [ ] 5.1.1 Create `buyer_invoices` table migration (with original_invoice_id, credit_reason)
- [ ] 5.1.2 Create `buyer_invoice_items` table migration (with tax fields, sort_order)
- [ ] 5.1.3 Create `buyer_payments` table migration
- [ ] 5.1.4 Create `BuyerInvoice` model with prepayment/balance/credit_note types
- [ ] 5.1.5 Create `BuyerInvoiceItem` model with tax calculation
- [ ] 5.1.6 Create `BuyerPayment` model with `HasMedia` trait for proof uploads
- [ ] 5.1.7 Create `BuyerInvoiceObserver` for totals calculation
- [ ] 5.1.8 Create `InvoiceType` enum (prepayment, balance, standard, credit_note, debit_note)
- [ ] 5.1.9 Create `InvoiceStatus` enum (draft, sent, partial, paid, overdue, cancelled)
- [ ] 5.1.10 Create `PaymentMethod` enum (bank_transfer, cash, check, lc, other)
- [ ] 5.1.11 Create `BuyerInvoicesRelationManager` for Request resource
- [ ] 5.1.12 Create `BuyerInvoiceItemsRelationManager` for BuyerInvoice resource
- [ ] 5.1.13 Add due date calculation (prepayment=issued_at, balance=delivery+net_days)
- [ ] 5.1.14 Add payment recording with required proof upload via Media Library
- [ ] 5.1.15 Add `amount_paid`, `amount_outstanding`, `days_overdue` accessors
- [ ] 5.1.16 Implement credit note creation from original invoice
- [ ] 5.1.17 Register media collection (payment_proof)
- [ ] 5.1.18 Write Pest tests for buyer invoices/payments/credit notes

### 5.2 Supplier Invoices & Payments
- [ ] 5.2.1 Create `supplier_invoices` table migration (with original_invoice_id, credit_reason)
- [ ] 5.2.2 Create `supplier_invoice_items` table migration (with tax fields, sort_order)
- [ ] 5.2.3 Create `supplier_payments` table migration
- [ ] 5.2.4 Create `SupplierInvoice` model with multi-currency and credit_note type
- [ ] 5.2.5 Create `SupplierInvoiceItem` model with tax calculation
- [ ] 5.2.6 Create `SupplierPayment` model with `HasMedia` trait for proof uploads
- [ ] 5.2.7 Create `SupplierInvoiceObserver` for totals calculation
- [ ] 5.2.8 Create `SupplierInvoicesRelationManager` for Request resource
- [ ] 5.2.9 Create `SupplierInvoiceItemsRelationManager` for SupplierInvoice resource
- [ ] 5.2.10 Add exchange rate snapshot at invoice time
- [ ] 5.2.11 Add payment recording with required proof upload via Media Library
- [ ] 5.2.12 Implement credit note creation from original invoice
- [ ] 5.2.13 Register media collection (payment_proof)
- [ ] 5.2.14 Write Pest tests for supplier invoices/payments/credit notes

### 5.3 Activity & Audit Logging ⭐ UPDATED

#### 5.3.1 Request Activity Timeline (User-Facing)
- [ ] 5.3.1.1 Create `request_activities` table migration
- [ ] 5.3.1.2 Create `RequestActivity` model
- [ ] 5.3.1.3 Create `ActivityType` enum for all activity types
- [ ] 5.3.1.4 Add activity logging to all request-related observers
- [ ] 5.3.1.5 Create Activity Log tab in Request resource
- [ ] 5.3.1.6 Write Pest tests for request activity logging

#### 5.3.2 System Audit Log (Spatie Activity Log) ⭐ NEW
- [ ] 5.3.2.1 Add `LogsActivity` trait to all ERP models
- [ ] 5.3.2.2 Configure `getActivitylogOptions()` for each model (logged fields)
- [ ] 5.3.2.3 Create auth event listeners for login/logout tracking with IP and user agent
- [ ] 5.3.2.4 Create `AuditLogResource` Filament resource (admin only)
- [ ] 5.3.2.5 Add filters: date range, user, entity type, action type
- [ ] 5.3.2.6 Add search: entity name, user name, description
- [ ] 5.3.2.7 Create Audit Detail modal with old/new values side-by-side
- [ ] 5.3.2.8 Add CSV export action for compliance reports
- [ ] 5.3.2.9 Write Pest tests for system audit log

### 5.4 P&L Calculation
- [ ] 5.4.1 Add `buyer_total` accessor to Request model
- [ ] 5.4.2 Add `supplier_cost` accessor (sum of supplier orders in base currency)
- [ ] 5.4.3 Add `gross_margin` accessor (buyer_total - supplier_cost)
- [ ] 5.4.4 Add `margin_percent` accessor
- [ ] 5.4.5 Add `amount_collected`, `amount_paid_out`, `net_cash_flow` accessors
- [ ] 5.4.6 Add Financial Summary widget to Request detail page
- [ ] 5.4.7 Write Pest tests for P&L calculations

## Phase 6: Dashboard & Polish

### 6.1 Dashboard
- [ ] 6.1.1 Create ERP Dashboard page or widgets
- [ ] 6.1.2 Add Active Requests KPI widget
- [ ] 6.1.3 Add Quotes Expiring widget with alert styling
- [ ] 6.1.4 Add Awaiting Payment widget
- [ ] 6.1.5 Add Monthly Revenue widget
- [ ] 6.1.6 Add Pipeline by Stage chart
- [ ] 6.1.7 Add Requires Attention list widget
- [ ] 6.1.8 Write Pest tests for dashboard widgets

### 6.2 Alerts & Notifications
- [ ] 6.2.1 Add quote expiration alerts (7 days, 3 days, 1 day)
- [ ] 6.2.2 Add payment overdue alerts
- [ ] 6.2.3 Add awaiting supplier quotes alerts
- [ ] 6.2.4 Add credit limit warning on order creation

### 6.3 PDF Generation
- [ ] 6.3.1 Create Buyer Quote PDF template (no supplier info)
- [ ] 6.3.2 Create Buyer Order confirmation PDF
- [ ] 6.3.3 Create Buyer Invoice PDF
- [ ] 6.3.4 Create Supplier Order (PO) PDF
- [ ] 6.3.5 Add download/email actions to Filament resources

### 6.4 Final Testing & Cleanup
- [ ] 6.4.1 Run full test suite and fix failures
- [ ] 6.4.2 Run PHPStan and fix type errors
- [ ] 6.4.3 Run Rector and Pint for code style
- [ ] 6.4.4 Add architecture tests for new ERP models
- [ ] 6.4.5 Verify 80% code coverage
- [ ] 6.4.6 Update README and documentation

## Task Summary

| Phase | Tasks | Focus |
|-------|-------|-------|
| 0 | 23 | Prerequisites (Spatie Permission, Settings, MorphMap, **Spatie Activity Log**) |
| 1 | 48 | Foundation (Tags, Currencies, **Tax Codes**, Buyers, Suppliers, Articles) |
| 2 | 21 | Request Management (Projects, Requests, Items with sort_order) |
| 3 | 26 | Multi-Supplier Quoting (with **item-level tax handling**) |
| 4 | 32 | Orders & Fulfillment (with tax, **outbound shipments**) |
| 5 | 40 | Finance & Journaling (with **System Audit Log**) |
| 6 | 20 | Dashboard & Polish |
| **Total** | **210** | |
