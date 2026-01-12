# ERP Trading System - Version History

> All version changes for schema, plan, and UI/UX documents are tracked here.
> Main documents contain only the current final state.

---

## Current Versions

| Document | Version | Description |
|----------|---------|-------------|
| schema.md | 3.3 | Database schema with migrations |
| plan.md | 3.3 | Business Requirements Document |
| uiux.md | 3.3 | UI/UX wireframes and flows |

---

## Changelog

### Version 3.3 (January 2025) - System Audit Log

**Schema Changes:**
- Added `spatie/laravel-activitylog` package to "To Install" list
- Added Section 10.2 "Spatie Activity Log (System Audit Trail)"
- Documented `LogsActivity` trait implementation for all ERP models
- Added auth event logging (login/logout with IP and user agent)

**Plan Changes:**
- Renamed Section 10.12 to "Activity & Audit Logging"
- Added Section 10.12.2 "System Audit Log (Spatie Activity Log)"
- Documented audit log captures: create, update, delete, login/logout
- Added audit log admin view requirements (filters, search, export)
- Listed all entities with audit logging enabled

**UI/UX Changes:**
- Added Section 9 "System Audit Log (Admin)" - New admin page
- Filters: date range, user, entity type, action type
- Search by entity name, user name, description
- Paginated list of all system activities
- Section 9.1 "Audit Detail Modal" - Shows old/new values side-by-side
- Export CSV for compliance reports
- Renumbered sections 10-13

**Audit Trail Covers:**
- All CRUD operations on all ERP entities
- Field-level change tracking (old value → new value)
- User identification (who made the change)
- IP address and browser tracking for auth events
- Queryable by date, user, entity, action type

---

### Version 3.2 (January 2025) - Invoice Items & Credit Notes

**Schema Changes:**
- Added `buyer_invoice_items` table - line items on buyer invoices
- Added `supplier_invoice_items` table - line items on supplier invoices
- Added `original_invoice_id` and `credit_reason` to invoice tables for credit notes
- Added `default_tax_code_id` to `supplier_quotes`, `buyer_quotes`, `buyer_orders`, `supplier_orders`
- Added item-level tax fields to all line item tables: `tax_code_id`, `is_tax_inclusive`, `tax_rate`, `unit_price_exc_tax`
- Added `sort_order` to all line item tables
- Total tables: 30 (was 28 in v3.1)

**UI/UX Changes:**
- Invoice Detail View showing line items with per-item tax breakdown
- Create Credit Note modal with item selection and quantity
- Create Invoice modal with line items and item-level tax controls
- Added [+ Credit Note] buttons on invoice cards

**Plan Changes:**
- Updated Tax Handling section for item-level tax
- Added TaxCalculationService requirement
- Added credit note scenarios to finance requirements

---

### Version 3.1 (January 2025) - Item-Level Tax & Outbound Shipments

**Schema Changes:**
- Added `tax_codes` table for tax dropdown options
- Added `default_tax_code_id` to `articles` table
- Added item-level tax fields: `tax_code_id`, `is_tax_inclusive`, `tax_rate`, `unit_price_exc_tax`
- Added `sort_order` to all item tables for explicit ordering
- Added `buyer_order_item_id` to `shipment_items` for outbound shipment support
- Total tables: 28

**Key Design Decisions:**
- Tax code dropdown instead of free-text tax rate
- Tax inclusive/exclusive toggle per line item
- Tax rate snapshotted at save time (future changes don't affect)
- Outbound shipments track delivery to buyer

---

### Version 3.0 (January 2025) - Relaticle CRM Integration

**Major Changes:**
- Built on existing Relaticle CRM foundation
- Reuse Spatie Media Library for file attachments (no custom `attachments` table)
- Reuse Spatie Settings for ERP configuration (no custom `settings` table)
- Install `spatie/laravel-permission` for RBAC (no custom role tables)
- Optional `company_id` FK linking buyers/suppliers to existing CRM companies
- Total tables: 27 (reduced from 35 via package reuse)

**Infrastructure Reuse:**
| Component | Using |
|-----------|-------|
| File attachments | Spatie Media Library (`media` table) |
| Settings | Spatie Settings (class-based) |
| RBAC | `spatie/laravel-permission` package |
| Custom fields | `relaticle/custom-fields` package |
| Multi-tenancy | Relaticle `HasTeam` trait |
| Creator tracking | Relaticle `HasCreator` trait |

---

### Version 2.0 (January 2024) - Multi-Supplier & Extensions

**Major Changes:**
- Multi-supplier projects (was single supplier)
- Renamed "Project" to "Request" as atomic unit
- Projects now group multiple Requests for large deals
- Flat shared categories (internal: `tags` table) replace hierarchical
- Single base currency for sales, multi-currency for purchases
- Quote extensions with reason logging (instead of cancellation)
- Manual payment/shipment journaling with file uploads
- Local tax support (default 11%)
- Role-based access control structure

**New Concepts:**
- Vague capture workflow: capture buyer request first, match articles later
- Supplier confidentiality: buyer never sees supplier info
- Exchange rate snapshot per transaction
- Quote validity extensions with audit trail

---

### Version 1.0 (January 2024) - Initial Requirements

**Initial Scope:**
- Single supplier per project
- Hierarchical product categories
- Single currency
- Basic quote/order/invoice flow
- Simple payment tracking

---

## Table Count History

| Version | Tables | Notes |
|---------|--------|-------|
| 1.0 | ~20 | Initial design |
| 2.0 | 35 | Full feature set |
| 3.0 | 27 | Reduced via Spatie packages |
| 3.1 | 28 | Added tax_codes |
| 3.2 | 30 | Added invoice items tables |
| 3.3 | 30 | Added Spatie Activity Log (no new custom tables) |

---

## Migration Path

When upgrading between versions:

**2.0 → 3.0:**
- Install `spatie/laravel-permission`
- Migrate custom attachments to Spatie Media Library
- Add `company_id` FK to buyers/suppliers (nullable)

**3.0 → 3.1:**
- Create `tax_codes` table and seed defaults
- Add tax fields to all item tables
- Add `sort_order` to item tables
- Add `buyer_order_item_id` to `shipment_items`

**3.1 → 3.2:**
- Create `buyer_invoice_items` table
- Create `supplier_invoice_items` table
- Add `original_invoice_id`, `type`, `credit_reason` to invoice tables
- Add `default_tax_code_id` to quote/order header tables

**3.2 → 3.3:**
- Install `spatie/laravel-activitylog` package
- Run package migrations (`activity_log` table created automatically)
- Add `LogsActivity` trait to all ERP models
- Configure logged fields per model
- Add auth event listeners for login/logout tracking
- Create Filament resource for Audit Log admin view
