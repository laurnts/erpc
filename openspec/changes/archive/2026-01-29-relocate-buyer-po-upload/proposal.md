# Change: Relocate Buyer PO Upload from Notes Section to Action Table

## Why
Currently, Buyer PO file upload is located in the Notes section of the buyer quote form, which is not intuitive. Users need to upload PO files after a quote is accepted, and having this functionality in the action table next to the PDF button provides better visibility and workflow alignment. The upload should only be available when the quote status is "Accepted", and the button should change to "View PO" when files are already uploaded.

## What Changes
- **REMOVED**: Buyer PO files upload field from Notes section in buyer quote form
- **ADDED**: Upload PO button in buyer quotes action table (next to PDF button)
- **ADDED**: Conditional display logic - show button only when Buyer Quote status is ACCEPTED
- **ADDED**: Dynamic button label - "Upload PO" when no files exist, "View PO" when files exist
- **ADDED**: Dynamic button functionality - "Upload PO" opens upload form, "View PO" opens view-only form
- **ADDED**: Slide-over form for uploading PO files (includes file upload component + file list)
- **ADDED**: Slide-over form for viewing PO files (file list only, no upload component)
- **MODIFIED**: Buyer PO list view component to work in slide-over context

## Impact
- Affected specs: `buyer-quotes` (new capability)
- Affected code:
  - `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php` - Remove PO upload from Notes section, add action button
  - `resources/views/filament/forms/components/buyer-po-list.blade.php` - May need updates for slide-over context
  - Existing routes and controllers remain unchanged
