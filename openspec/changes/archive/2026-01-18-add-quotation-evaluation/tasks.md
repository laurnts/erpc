# Tasks: Add Quotation Evaluation

## 1. Master Data - Key Accounts
- [x] 1.1 Create migration for `key_accounts` table (name, email, phone, team_id)
- [x] 1.2 Create `KeyAccount` model with team relationship
- [x] 1.3 Create `KeyAccountResource` for Filament admin (Master Data section)
  - List page with columns: Name, Email, Phone
  - Create/Edit form
  - Delete action

## 2. Database - Quotation Evaluations
- [x] 2.1 Create migration for `quotation_evaluations` table
  - Fields: id, team_id, request_id, qe_number, description, qe_date
  - Foreign keys: prepared_by_id, dept_head_sales_id, deputy_director_id, approved_by_id, created_by
  - JSON: data (snapshot of items, suppliers, totals)
  - Timestamps
- [x] 2.2 Create `QuotationEvaluation` model with relationships
- [x] 2.3 Add QE number generation helper (roman numerals for months)

## 3. Quotation Evaluation Resource
- [x] 3.1 Create `QuotationEvaluationResource` in Master Data section
- [x] 3.2 Create List page with columns:
  - QE Number, Request Number, Description, Date, Created By
- [x] 3.3 Create View page with sections:
  - QE Information (number, date, request link, description)
  - Item Comparison table (from snapshot data)
  - Supplier Information table
  - Central Purchasing section
  - Created by / timestamp footer
- [x] 3.4 Create Edit page for Central Purchasing fields only
- [x] 3.5 Style Item Comparison table with Subtotal, Tax, Grand Total rows
- [x] 3.6 Make Item Comparison, Supplier Information, Central Purchasing sections full width
- [x] 3.7 Move Supplier Information section after Item Comparison section
- [x] 3.8 Display unit price and item total (before tax) for each item in comparison table
- [x] 3.9 Add Download PDF button in header actions (landscape A4 format)

## 4. QE Creation Form (Livewire)
- [x] 4.1 Create `QuotationEvaluationForm` Livewire component
- [x] 4.2 Implement QE Information section (number placeholder, date, request, description)
- [x] 4.3 Implement Central Purchasing section with Key Account selects
- [x] 4.4 Implement Key Account inline create modal
- [x] 4.5 Implement save functionality:
  - Generate QE number
  - Snapshot item comparison data with totals
  - Snapshot supplier information
  - Save to database
  - Redirect to QE view page

## 5. UI Integration
- [x] 5.1 Add "Create QE" button to SupplierQuoteComparison component
- [x] 5.2 Wire up slide-over modal for QE creation form
- [x] 5.3 Implement redirect to QE view page after save

## 6. PDF Export
- [x] 6.1 Create PDF template `resources/views/pdf/quotation-evaluation.blade.php`
- [x] 6.2 PDF includes QE Information header section
- [x] 6.3 PDF includes Item Comparison table with unit price and item total
- [x] 6.4 PDF includes Supplier Information table
- [x] 6.5 PDF includes Central Purchasing approval section with signature lines
- [x] 6.6 Sanitize QE number for filename (replace / with -)

## 7. Testing & Verification
- [ ] 7.1 Test QE number generation with roman numerals
- [ ] 7.2 Test Key Account creation from inline modal
- [ ] 7.3 Test data snapshot includes all items and suppliers
- [ ] 7.4 Test Subtotal, Tax, Grand Total calculations in view
- [ ] 7.5 Test redirect to view page after creation
- [ ] 7.6 Test request number link navigation
- [ ] 7.7 Test PDF download in landscape format
