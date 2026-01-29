## 1. Remove Buyer PO Upload from Notes Section
- [x] 1.1 Remove `FileUpload::make('buyer_po_files')` component from Notes section in `BuyerQuotesRelationManager.php`
- [x] 1.2 Remove `ViewField::make('buyer_po_list')` component from Notes section
- [x] 1.3 Clean up any related form state handling for PO files in Notes section

## 2. Add Upload PO Action Button
- [x] 2.1 Create new Action in `recordActions` array (after PDF button)
- [x] 2.2 Set button visibility condition: only show when `status === BuyerQuoteStatus::ACCEPTED`
- [x] 2.3 Set dynamic label: "Upload PO" when no files exist, "View PO" when files exist
- [x] 2.4 Configure action to use `slideOver()` for slide form display
- [x] 2.5 Add appropriate icon (e.g., `heroicon-o-document-arrow-up` for upload, `heroicon-o-eye` for view)
- [x] 2.6 Implement conditional form display: upload form when no files, view-only form when files exist

## 3. Create Slide-Over Form for PO Upload/View
- [x] 3.1 Create form schema method for PO upload (`getBuyerPoUploadFormSchema`) - includes FileUpload + ViewField
- [x] 3.2 Create form schema method for PO view-only (`getBuyerPoViewFormSchema`) - includes ViewField only
- [x] 3.3 Include `FileUpload` component for uploading new PO files in upload form
- [x] 3.4 Include `ViewField` component for displaying uploaded PO files list in both forms
- [x] 3.5 Configure upload form to handle file uploads and save to media collection
- [x] 3.6 Implement conditional form selection based on file existence
- [x] 3.7 Ensure forms work correctly in slide-over context

## 4. Update Buyer PO List View Component
- [x] 4.1 Verify `buyer-po-list.blade.php` works correctly in slide-over context
- [x] 4.2 Test file download and delete functionality from slide-over
- [x] 4.3 Ensure proper refresh/reload after file operations

## 5. Testing
- [x] 5.1 Test Upload PO button appears only when status is ACCEPTED
- [x] 5.2 Test button label changes from "Upload PO" to "View PO" when files exist
- [x] 5.3 Test Upload PO button opens upload form (with FileUpload component) when no files exist
- [x] 5.4 Test View PO button opens view-only form (no FileUpload component) when files exist
- [x] 5.5 Test slide-over form opens correctly
- [x] 5.6 Test file upload functionality in slide-over
- [x] 5.7 Test file viewing/downloading from slide-over
- [x] 5.8 Test file deletion from slide-over
- [x] 5.9 Verify Notes section no longer shows PO upload fields
