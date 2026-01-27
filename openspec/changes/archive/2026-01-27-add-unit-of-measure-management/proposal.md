# Change: Add Unit of Measure Management

## Why
Currently, units of measure (e.g., "pcs", "kg", "meter", "box") are entered manually as text fields across multiple forms. This leads to:
- Inconsistent data entry (typos, variations like "pcs" vs "PCS" vs "pieces")
- No centralized management of available units
- Difficult to standardize units across the system
- Hard to maintain and update unit labels

By introducing a centralized Unit of Measure management system similar to Tax Codes, we can:
- Standardize unit entry across all forms
- Provide a consistent admin interface for managing units
- Enable better data quality and reporting
- Allow teams to customize their unit list

## What Changes
- **ADDED**: New `unit_of_measures` table with fields: code, label, is_active, is_default, sort_order
- **ADDED**: UnitOfMeasure model with team scoping (HasTeam, HasCreator traits)
- **ADDED**: UnitOfMeasureResource for CRUD operations under Settings menu
- **ADDED**: UnitOfMeasureObserver to handle team_id, creator_id, and default unit logic
- **ADDED**: UnitOfMeasurePolicy for authorization
- **ADDED**: UnitOfMeasureSeeder for default units (pcs, kg, mt, set, box, roll, pair, l, m)
- **ADDED**: `unit_label` accessor to all item models for consistent display
- **MODIFIED**: All unit fields changed from TextInput to Select dropdowns
- **MODIFIED**: Database migrations to convert string unit fields to foreign keys
- **MODIFIED**: Forms affected:
  - ArticleResource (form and view page)
  - ItemsRelationManager (Request items)
  - BuyerQuotesRelationManager
  - SupplierQuotesRelationManager
  - SupplierOrdersRelationManager
  - BuyerOrdersRelationManager
  - BuyerOrdersRelationManager (view page with unit display)
- **MODIFIED**: SafeUnitCast updated to work with UnitOfMeasure model (backward compatible)
- **MODIFIED**: All PDF templates updated to use unit_label:
  - buyer-order.blade.php
  - buyer-quote.blade.php
  - supplier-order.blade.php
  - profit-and-loss.blade.php
- **MODIFIED**: ShipmentItem::getUnit() updated to use unit_label accessor
- **MODIFIED**: ShipmentsRelationManager updated to display unit_label
- **MODIFIED**: All item creation methods updated to copy unit_of_measure_id and set unit properly
- **MODIFIED**: Observers updated to sync unit field from unit_of_measure_id
- **MODIFIED**: Unit enum kept for backward compatibility (to be deprecated later)

## Impact
- **Affected specs**: `erp-trading-core` (new requirement for Unit of Measure management)
- **Affected code**:
  - Database: 8 tables migrated with unit_of_measure_id foreign keys
  - Models: Article, RequestItem, BuyerQuoteItem, SupplierQuoteItem, BuyerOrderItem, SupplierOrderItem, BuyerInvoiceItem, SupplierInvoiceItem, ShipmentItem
  - Resources: ArticleResource, RequestResource relation managers, Quote/Order relation managers, ShipmentsRelationManager
  - Casts: SafeUnitCast (updated for backward compatibility)
  - Observers: BuyerQuoteItemObserver, SupplierQuoteItemObserver, UnitOfMeasureObserver
  - PDF Templates: buyer-order.blade.php, buyer-quote.blade.php, supplier-order.blade.php, profit-and-loss.blade.php
- **Breaking changes**: None (migration handles data conversion, backward compatible)
- **Migration required**: Yes - existing string unit values mapped to UnitOfMeasure records
- **Status**: ✅ **IMPLEMENTED** - All core functionality complete, testing pending
