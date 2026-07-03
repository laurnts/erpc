# Change: Add Service Request Type with Acceptance Reports

## Why
Currently, the system only supports Goods requests with a workflow that includes inbound shipments and quotation evaluations. Business requirements need support for Service requests that have a different workflow:
- No inbound shipment flow (replaced by acceptance reports)
- Support for main items with child items (hierarchical structure)
- No quotation evaluation creation
- Acceptance reports with file uploads (PDF, Word, images)

This change enables the system to handle both Goods and Service request types with appropriate workflows for each.

## What Changes
- **ADDED**: Request type field (Goods/Service) in Request Details section
- **ADDED**: Acceptance Report model and resource for Service requests
- **ADDED**: Child items support for Service request items (parent-child relationship)
- **ADDED**: Conditional form logic in RequestItem form based on request type
- **MODIFIED**: Request workflow to skip quotation evaluation and inbound shipments for Service requests
- **MODIFIED**: RequestItem form to show child items section when request type is Service
- **MODIFIED**: Request stage transitions to accommodate Service workflow differences

## Impact
- **Affected specs**: `erp-trading-core` (requests, request items, workflow)
- **Affected code**:
  - `app/Models/Request.php` - Add request_type field
  - `app/Models/RequestItem.php` - Add parent-child relationship
  - `app/Models/AcceptanceReport.php` - New model
  - `app/Filament/Resources/RequestResource.php` - Add request type field
  - `app/Filament/Resources/RequestResource/RelationManagers/ItemsRelationManager.php` - Conditional form logic
  - `app/Filament/Resources/AcceptanceReportResource.php` - New resource
  - `app/Enums/RequestStage.php` - Workflow modifications
  - Database migrations for new fields and tables
- **Breaking changes**: None (backward compatible, defaults to Goods)
