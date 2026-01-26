# Implementation Tasks

## 1. DO Number Generation

- [x] 1.1 Add `do_number` column to `shipments` table (nullable string)
  - Create migration: `database/migrations/2026_01_26_034231_add_do_number_to_shipments_table.php`
  - Add to `Shipment` model `$fillable` array
- [x] 1.2 Implement `generateDoNumber()` method in `Shipment` model
  - Format: `{4digit_increment}-CP/DO/{roman_month}/{year}`
  - Use `RomanNumerals::month()` utility
  - Query existing DO numbers for same team/month/year to get next increment
  - Store generated DO number in `do_number` field
- [x] 1.3 Add `getDoNumber()` method to ensure DO number exists
  - Auto-generate if not set when accessed
  - Return cached value if already generated

## 2. PDF Generation Service

- [x] 2.1 Add `generateShipmentDeliveryOrderPdf()` method to `PdfGenerationService`
  - Load shipment with relationships: `supplierOrder`, `supplierOrder.supplier`, `request.buyer`, `items.supplierOrderItem.article`
  - Prepare data for PDF template
  - Generate PDF using `Pdf::loadView()`
  - Set paper size to A4 landscape
  - Return PDF output string
- [x] 2.2 Add `getShipmentDeliveryOrderFilename()` method
  - Format: `DO_{do_number}.pdf`
  - Sanitize DO number for filename (replace "/" and "\" with "-")

## 3. PDF Template

- [x] 3.1 Create `resources/views/pdf/shipment-delivery-order.blade.php`
  - Extend `pdf.layout` template
  - Header section with company info and DO number
  - Document info: DO number, current date, PO number
  - Buyer information section
  - Items table with columns: Number, Item Name, Brand, Model, Qty, Remarks
  - Delivery address section (buyer address)
  - Central purchasing signature section:
    - Prepared By (blank line)
    - Acknowledged By Head Admin (blank line)
    - Delivered By (blank line)
    - Accepted By (blank line)
    - Notes (shipment notes if available)
- [x] 3.2 Style the template to match existing PDF formats
  - Use similar styling as `supplier-order.blade.php`
  - Ensure proper table formatting
  - Add signature lines with labels

## 4. Filament Integration

- [x] 4.1 Update `ShipmentsRelationManager.php`
  - Add PDF download button in "View Shipments" modal for each shipment
  - Use `Placeholder` component with HTML button linking to PDF route
  - Ensure button is visible for inbound shipments only
- [x] 4.2 Update `DownloadPdfAction.php`
  - Add `Shipment` model case to match statement
  - Call `generateShipmentDeliveryOrderPdf()` for shipments
  - Use `getShipmentDeliveryOrderFilename()` for filename
- [x] 4.3 Create `ShipmentPdfController` and route
  - Create controller: `app/Http/Controllers/ShipmentPdfController.php`
  - Add route: `/shipments/{shipment}/pdf` with name `shipment.pdf`
  - Add authentication and team access checks

## 5. Data Relationships

- [x] 5.1 Ensure proper data loading in PDF generation
  - Shipment -> SupplierOrder -> Request -> Buyer (Company)
  - ShipmentItem -> SupplierOrderItem -> Article (for brand/model)
  - Handle null cases gracefully (missing article, missing buyer address)
- [x] 5.2 Map item data correctly
  - Item Name: `supplierOrderItem->description`
  - Brand: `article->attributes['brand']` (if article exists)
  - Model: `article->attributes['model']` (if article exists)
  - Qty: `shipmentItem->quantity_shipped`
  - Remarks: `shipmentItem->condition_notes` or `supplierOrderItem->notes`

## 6. Testing

- [x] 6.1 Create unit test for DO number generation
  - Test format correctness
  - Test increment logic
  - Test roman numeral month conversion
  - Test cached DO number retrieval
  - Test auto-generation when accessed
- [x] 6.2 Create feature test for PDF generation
  - Test PDF generation for shipment with all data
  - Test PDF generation with missing optional data (article, buyer address)
  - Test PDF filename generation and sanitization
  - Test PDF content includes all required sections
  - Test item table with brand/model data
- [x] 6.3 Manual testing
  - Generate PDF from Filament interface
  - Verify all fields display correctly
  - Verify PDF layout and styling (landscape orientation)
  - Test with various shipment scenarios

## Summary

| Phase | Tasks | Files |
|-------|-------|-------|
| Database & Model | 3 | 2 |
| PDF Service | 2 | 1 |
| PDF Template | 2 | 1 |
| Filament Integration | 3 | 3 |
| Data Mapping | 2 | 1 |
| Testing | 3 | 2 |

**Total: 15 tasks, 11 files modified/created**

### Test Coverage
- **Unit Tests**: 6 tests for DO number generation (all passing)
- **Feature Tests**: 8 tests for PDF generation (all passing)
- **Total**: 14 tests, 35 assertions
