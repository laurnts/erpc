# Change: Update Request View Page Layout

## Why
The current Request view page displays Financial Summary and Requested Items in a 2-column layout. Users need better visibility into Payment Terms and Shipment information directly on the view page, without having to navigate to relation managers. This improves the information density and reduces navigation overhead.

## What Changes
- **REMOVED**: Requested Items section from the summary area
- **MODIFIED**: Layout changed from 2 columns to 3 columns
- **ADDED**: Payment Terms section displaying:
  - Prepayment value (percentage or fixed amount based on prepayment type)
  - List of payment terms with due days and percentage
  - Payment status (Paid/Not Paid) for each payment term
- **ADDED**: Shipment section displaying:
  - List of shipments with shipment number, status, carrier name, and tracking number

## Impact
- Affected specs: `erp-quoting` (Request view display)
- Affected code:
  - `app/Filament/Resources/RequestResource/Pages/ViewRequest.php` (infolist method)
  - Payment terms data sourced from BuyerQuote via BuyerOrder relationship
  - Shipment data sourced from Request->shipments() relationship
