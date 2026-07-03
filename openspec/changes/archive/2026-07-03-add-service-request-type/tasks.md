# Implementation Tasks

## 1. Database Schema
- [x] 1.1 Create migration to add `request_type` column to `requests` table (enum: goods, service, default: goods)
- [x] 1.2 Create migration to add `parent_id` column to `request_items` table (nullable foreign key to request_items)
- [x] 1.3 Create migration for `acceptance_reports` table with fields:
  - id, request_id, report_number, reported_at, reported_by, notes, created_at, updated_at, deleted_at
- [x] 1.4 Create migration for `acceptance_report_items` table (pivot for request items)
- [x] 1.5 Add indexes for performance (request_type, parent_id)

## 2. Models and Enums
- [x] 2.1 Create `RequestType` enum (Goods, Service)
- [x] 2.2 Update `Request` model:
  - Add `request_type` to fillable and casts
  - Add relationship methods: `acceptanceReports()`, `isServiceRequest()`, `isGoodsRequest()`
  - Update stage transition logic to skip quotation evaluation for Service requests
- [x] 2.3 Update `RequestItem` model:
  - Add `parent_id` to fillable
  - Add relationships: `parent()`, `children()`
  - Add methods: `isMainItem()`, `isChildItem()`, `getMainItem()`
- [x] 2.4 Create `AcceptanceReport` model with:
  - Relationships: `request()`, `items()` (via pivot)
  - Media collection for file uploads (PDF, Word, images)
  - Auto-generate report_number

## 3. Filament Resources
- [x] 3.1 Update `RequestResource::getFormSchema()`:
  - Add `request_type` Select field in Request Details section
  - Make it required, default to Goods
- [x] 3.2 Update `ItemsRelationManager`:
  - Add conditional logic to show/hide fields based on request type
  - When Service: Show child items section under "Match to Article"
  - Child items form: description, quantity, unit_of_measure_id
  - Add `parent_id` hidden field for child items
- [x] 3.3 Create `AcceptanceReportResource`:
  - List page with filters (request, date range)
  - Create/Edit form with:
    - Request (readonly if editing)
    - Report number (auto-generated)
    - Reported date
    - Reported by (user)
    - Notes
    - File upload (PDF, Word, images)
    - Items selection (request items)
  - View page with file preview/download
- [x] 3.4 Add `AcceptanceReportsRelationManager` to `RequestResource`:
  - Only visible for Service requests
  - Shows list of acceptance reports for the request
  - Create action opens modal/form

## 4. Workflow Modifications
- [x] 4.1 Update `RequestStage` enum:
  - Modify stage transition logic to skip quotation evaluation stage for Service requests
  - Add method `canCreateQuotationEvaluation()` that returns false for Service requests
- [x] 4.2 Update `RequestObserver` or stage transition logic:
  - Prevent quotation evaluation creation for Service requests
  - Skip inbound shipment stage for Service requests
- [x] 4.3 Update relation managers visibility:
  - Hide `QuotationEvaluationsRelationManager` for Service requests
  - Hide `ShipmentsRelationManager` for Service requests (or show only outbound)
  - Show `AcceptanceReportsRelationManager` only for Service requests

## 5. Business Logic
- [x] 5.1 Update `RequestItem` form validation:
  - For Service requests: Main item must have article_id, child items don't need article_id
  - For Goods requests: All items must have article_id (existing behavior)
- [x] 5.2 Update "Send to Suppliers" action:
  - For Service requests: Only send main items (not child items)
  - For Goods requests: Send all items (existing behavior)
- [x] 5.3 Update stage transition validation:
  - For Service requests: Skip "all items matched" check (only main items need matching)
  - For Goods requests: Keep existing validation

## 6. UI/UX Enhancements
- [x] 6.1 Add visual indicator in Request list/table for request type
- [x] 6.2 Update Request view page to show appropriate tabs based on request type
- [x] 6.3 Add helper text in forms explaining Service vs Goods differences
- [x] 6.4 Style child items differently in RequestItem table (indentation or badge)

## 7. Testing
- [x] 7.1 Write feature tests for Service request creation
- [x] 7.2 Write tests for child items creation and management
- [x] 7.3 Write tests for acceptance report creation and file upload
- [x] 7.4 Write tests for workflow differences (no quotation evaluation, no inbound shipments)
- [x] 7.5 Write tests for backward compatibility (existing Goods requests still work)
- [x] 7.6 Write tests for form conditional logic

## 8. Documentation
- [x] 8.1 Update README.md with Service request type information
- [x] 8.2 Document acceptance report workflow
- [x] 8.3 Document child items structure and usage
