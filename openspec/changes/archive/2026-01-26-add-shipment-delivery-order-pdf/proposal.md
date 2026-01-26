# Change: Add Delivery Order PDF for Inbound Shipments

## Why

Users need to generate Delivery Order (DO) documents for inbound shipments to track deliveries from suppliers. Currently, there is no way to generate a standardized DO document that includes shipment details, items, buyer information, and delivery addresses. This feature will enable users to:

1. Generate professional DO documents directly from shipment records
2. Track deliveries with proper documentation
3. Include all necessary information for delivery acceptance
4. Maintain consistency with other ERP document formats (PO, Quotes, etc.)

## What Changes

### PDF Generation Service
- **ADDED** `generateShipmentDeliveryOrderPdf()` method in `PdfGenerationService` (A4 landscape)
- **ADDED** `getShipmentDeliveryOrderFilename()` method for PDF filename generation (sanitizes "/" and "\" characters)

### PDF Template
- **ADDED** `resources/views/pdf/shipment-delivery-order.blade.php` - Blade template for DO PDF

### DO Number Generation
- **ADDED** `generateDoNumber()` method in `Shipment` model
- Format: `{4digit_increment}-CP/DO/{roman_month}/{year}`
- Auto-generated when PDF is first generated (stored in shipment if needed)

### Filament Integration
- **MODIFIED** `ShipmentsRelationManager.php` - Add PDF download button in shipment view modal
- **ADDED** `ShipmentPdfController` - Controller for PDF download route
- **ADDED** Route `/shipments/{shipment}/pdf` with name `shipment.pdf`

### DownloadPdfAction Support
- **MODIFIED** `DownloadPdfAction.php` - Add support for `Shipment` model type

## Impact

- Affected specs: `erp-shipments`
- Affected code:
  - `app/Services/Erp/PdfGenerationService.php` - New PDF generation method
  - `app/Models/Shipment.php` - DO number generation method
  - `app/Filament/Resources/RequestResource/RelationManagers/ShipmentsRelationManager.php` - PDF button in modal
  - `app/Filament/Actions/DownloadPdfAction.php` - Shipment model support
  - `app/Http/Controllers/ShipmentPdfController.php` - PDF download controller
  - `routes/web.php` - PDF download route
  - `resources/views/pdf/shipment-delivery-order.blade.php` - New PDF template (landscape)
  - `database/migrations/2026_01_26_034231_add_do_number_to_shipments_table.php` - Database migration

## Breaking Changes

None. This is a new feature addition.

## PDF Content Requirements

The Delivery Order PDF SHALL include:

1. **DO Number**: Format `{4digit_increment}-CP/DO/{roman_month}/{year}` (e.g., `0001-CP/DO/I/2025`)
2. **Current Date**: Date when PDF is generated
3. **PO Number**: From associated supplier order
4. **Buyer Name**: From request's buyer company
5. **Item Table**: Based on shipment items with columns:
   - Number (sequence)
   - Item Name (from supplier order item description)
   - Brand (from article, if available)
   - Model (from article, if available)
   - Qty (quantity shipped)
   - Remarks (condition notes or item notes)
6. **Delivery Address**: Buyer's address from company record
7. **Central Purchasing Section**: Signature fields for:
   - Prepared By
   - Acknowledged By Head Admin
   - Delivered By
   - Accepted By
   - Notes (shipment notes)
