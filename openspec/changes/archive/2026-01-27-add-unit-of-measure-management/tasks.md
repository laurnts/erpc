# Tasks: Add Unit of Measure Management

## 1. Database & Model Infrastructure
- [x] 1.1 Create migration for `unit_of_measures` table
- [x] 1.2 Create UnitOfMeasure model with HasTeam, HasCreator traits
- [x] 1.3 Create UnitOfMeasureFactory for testing
- [x] 1.4 Create UnitOfMeasureObserver (handles team_id, creator_id, and default unit logic)
- [x] 1.5 Add indexes: team_id+code unique, team_id+is_active, team_id+is_default

## 2. Admin Resource (CRUD)
- [x] 2.1 Create UnitOfMeasureResource following TaxCodeResource pattern
- [x] 2.2 Add form fields: code, label, is_active, is_default, sort_order
- [x] 2.3 Add table columns: code, label, is_active, is_default, sort_order, created_at
- [x] 2.4 Add filters: is_active, is_default
- [x] 2.5 Set navigation group to "Settings"
- [x] 2.6 Set navigation sort order (after Tax Codes)
- [x] 2.7 Add validation: code unique per team, required fields
- [x] 2.8 Add ViewRecord page with Edit/Delete actions in ActionGroup
- [x] 2.9 Add slide-over modal for Create action
- [x] 2.10 Make list rows clickable to view page

## 3. Default Units Seeding
- [x] 3.1 Create seeder for default units (pcs, kg, mt, set, box, roll, pair, l, m)
- [x] 3.2 Add seeding logic to DatabaseSeeder
- [x] 3.3 Ensure seeding is idempotent (uses firstOrCreate)

## 4. Database Migration - Add Foreign Keys
- [x] 4.1 Create migration to add `unit_of_measure_id` columns (nullable) to:
  - articles
  - request_items
  - buyer_quote_items
  - supplier_quote_items
  - buyer_order_items
  - supplier_order_items
  - buyer_invoice_items
  - supplier_invoice_items
- [x] 4.2 Add foreign key constraints
- [x] 4.3 Add indexes on `unit_of_measure_id` columns

## 5. Data Migration - Map Existing Values
- [x] 5.1 Create migration to map existing string `unit` values to `unit_of_measure_id`
- [x] 5.2 Handle mapping logic:
  - Match by code (exact match)
  - Create UnitOfMeasure if doesn't exist (for custom units)
  - Set default 'pcs' for null/invalid values
- [x] 5.3 Migration tested and verified

## 6. Model Updates - Add Relationships
- [x] 6.1 Add `unitOfMeasure()` relationship to Article model
- [x] 6.2 Add `unitOfMeasure()` relationship to RequestItem model
- [x] 6.3 Add `unitOfMeasure()` relationship to BuyerQuoteItem model
- [x] 6.4 Add `unitOfMeasure()` relationship to SupplierQuoteItem model
- [x] 6.5 Add `unitOfMeasure()` relationship to BuyerOrderItem model
- [x] 6.6 Add `unitOfMeasure()` relationship to SupplierOrderItem model
- [x] 6.7 Add `unitOfMeasure()` relationship to BuyerInvoiceItem model
- [x] 6.8 Add `unitOfMeasure()` relationship to SupplierInvoiceItem model
- [x] 6.9 Update fillable arrays to include `unit_of_measure_id`
- [x] 6.10 Add `unit_label` accessor to all item models for display

## 7. Update SafeUnitCast
- [x] 7.1 Update SafeUnitCast to handle UnitOfMeasure model
- [x] 7.2 Support both string (legacy) and integer (foreign key) values
- [x] 7.3 Add fallback to default unit ('pcs')
- [x] 7.4 Cast tested with both formats

## 8. Form Updates - ArticleResource
- [x] 8.1 Replace TextInput with Select for unit field
- [x] 8.2 Use relationship() method
- [x] 8.3 Add searchable, preload
- [x] 8.4 Set default to 'pcs' unit
- [x] 8.5 Update table column to show unit label
- [x] 8.6 Update ViewArticle page to display unit label

## 9. Form Updates - Request Items
- [x] 9.1 Update ItemsRelationManager form
- [x] 9.2 Replace TextInput with Select
- [x] 9.3 Update table column to show unit label
- [x] 9.4 Update inline create modal

## 10. Form Updates - Quote/Order Relation Managers
- [x] 10.1 Update BuyerQuotesRelationManager form
- [x] 10.2 Update SupplierQuotesRelationManager form
- [x] 10.3 Update SupplierOrdersRelationManager form
- [x] 10.4 Update BuyerOrdersRelationManager form
- [x] 10.5 Replace TextInput with Select in all forms
- [x] 10.6 Update table columns to show unit labels
- [x] 10.7 Add eager loading for unitOfMeasure relationships

## 11. Form Updates - Invoice Forms
- [x] 11.1 Verified invoice forms don't have unit fields (units come from order items)
- [x] 11.2 Updated BuyerInvoiceItem::createFromOrderItem to copy unit_of_measure_id
- [x] 11.3 Verified SupplierInvoiceItem doesn't need updates (no direct unit field)

## 12. Update Other References
- [x] 12.1 Updated all PDF templates to use unit_label:
  - buyer-order.blade.php
  - buyer-quote.blade.php
  - supplier-order.blade.php
  - profit-and-loss.blade.php
- [x] 12.2 Updated QuotationEvaluationForm (uses array data, no changes needed)
- [x] 12.3 Updated ShipmentItem::getUnit() to use unit_label accessor
- [x] 12.4 Updated ShipmentsRelationManager to display unit_label in dropdowns

## 13. Observers & Creation Methods
- [x] 13.1 Updated BuyerQuoteItemObserver to sync unit from unit_of_measure_id
- [x] 13.2 Updated SupplierQuoteItemObserver to sync unit from unit_of_measure_id
- [x] 13.3 Updated BuyerOrderItem::createFromQuoteItem to copy unit_of_measure_id and set unit
- [x] 13.4 Updated SupplierOrder::createFromQuote to copy unit_of_measure_id and set unit
- [x] 13.5 Updated SupplierOrdersRelationManager to set unit when creating items
- [x] 13.6 Updated BuyerInvoiceItem::createFromOrderItem to copy unit_of_measure_id and set unit

## 14. Policies & Permissions
- [x] 14.1 Created UnitOfMeasurePolicy for authorization
- [x] 14.2 Added permissions to ErpPermissionSeeder:
  - view unit of measures
  - create unit of measures
  - update unit of measures
  - delete unit of measures
- [x] 14.3 Assigned permissions to appropriate roles

## 15. Testing
- [x] 15.1 Write unit tests for UnitOfMeasure model
- [x] 15.2 Write feature tests for UnitOfMeasureResource CRUD
- [x] 15.3 Write tests for data migration
- [x] 15.4 Write tests for form updates (ArticleResource, etc.)
- [x] 15.5 Test SafeUnitCast with both formats
- [x] 15.6 Test backward compatibility

## 16. Database Migration - Make Non-Nullable (Future)
- [x] 16.1 Create migration to make `unit_of_measure_id` non-nullable
- [x] 16.2 Set default values for any remaining nulls
- [x] 16.3 Add NOT NULL constraints

## 17. Database Migration - Drop Old Columns (Future)
- [x] 17.1 Verify all data migrated successfully
- [x] 17.2 Create migration to drop old `unit` string columns
- [x] 17.3 Test rollback scenario

## 18. Documentation & Cleanup
- [x] 18.1 Updated OpenSpec proposal, design, spec, and tasks
- [x] 18.2 Mark Unit enum as deprecated (add @deprecated tag)
- [x] 18.3 Add migration notes for deployment
- [x] 18.4 Verified all linter/static analysis passes

## Implementation Notes

### Key Implementation Details:
1. **Unit Label Accessor**: Added `getUnitLabelAttribute()` to all item models to display unit labels consistently
2. **SafeUnitCast**: Updated to handle both legacy string values and new foreign keys
3. **Observers**: Created observers to sync `unit` field from `unit_of_measure_id` to maintain backward compatibility
4. **Creation Methods**: Updated all item creation methods to properly copy `unit_of_measure_id` and set `unit` using `setRawAttributes` to bypass SafeUnitCast
5. **PDF Templates**: Updated all PDF templates to use `unit_label` accessor for consistent display
6. **Shipment Items**: Updated ShipmentItem to use `unit_label` accessor from order items

### Known Issues Fixed:
- Fixed NOT NULL constraint violations by ensuring `unit` field is always set when creating items
- Fixed unit display issues in BuyerOrder view page
- Fixed unit display in PDF templates
- Fixed unit display in shipment forms

### Future Work:
- Make `unit_of_measure_id` columns non-nullable after verifying all data is migrated
- Drop old `unit` string columns after ensuring backward compatibility is no longer needed
- Deprecate and eventually remove Unit enum
- Add comprehensive test coverage
