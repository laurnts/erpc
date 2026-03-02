# Design: Service Request Type Implementation

## Context
The current ERP system handles Goods requests with a workflow that includes:
1. Request creation with items
2. Matching items to articles
3. Supplier quoting
4. Quotation evaluation
5. Buyer quoting
6. Buyer order confirmation
7. Supplier order creation
8. Inbound shipment tracking
9. Outbound shipment to buyer
10. Invoicing and payment

Service requests require a different workflow:
- No quotation evaluation (direct to buyer quote)
- No inbound shipment (replaced by acceptance reports)
- Hierarchical items (main item with child items)
- File-based acceptance reports

## Goals
- Support both Goods and Service request types
- Maintain backward compatibility (existing requests default to Goods)
- Provide clear UI differentiation between request types
- Enable acceptance report workflow for Service requests
- Support hierarchical item structure for Service requests

## Non-Goals
- Changing existing Goods request workflow
- Supporting mixed Goods/Service items in same request
- Real-time collaboration on acceptance reports
- Complex approval workflows for acceptance reports (future enhancement)

## Decisions

### Decision 1: Request Type Storage
**Chosen**: Store `request_type` as enum column in `requests` table
**Alternatives considered**:
- Separate tables for GoodsRequest/ServiceRequest (rejected - too complex, breaks existing queries)
- Polymorphic relationship (rejected - unnecessary complexity)
**Rationale**: Simple enum field is sufficient, easy to query, maintains single table inheritance pattern

### Decision 2: Child Items Structure
**Chosen**: Self-referential relationship in `request_items` table with `parent_id`
**Alternatives considered**:
- Separate `request_item_children` table (rejected - adds join complexity)
- JSON field for child items (rejected - harder to query and maintain)
**Rationale**: Standard relational pattern, easy to query, supports unlimited nesting if needed later

### Decision 3: Acceptance Report Model
**Chosen**: Separate `AcceptanceReport` model with many-to-many relationship to request items
**Alternatives considered**:
- Add fields to Request model (rejected - multiple reports per request)
- Use Shipment model with type flag (rejected - conceptually different, would confuse)
**Rationale**: Clear separation of concerns, supports multiple acceptance reports per request

### Decision 4: File Upload Storage
**Chosen**: Use Spatie Media Library (already in use) for acceptance report files
**Alternatives considered**:
- Direct file storage (rejected - no existing pattern)
- External storage service (rejected - unnecessary complexity)
**Rationale**: Consistent with existing media handling, supports multiple file types, built-in validation

### Decision 5: Workflow Stage Modifications
**Chosen**: Conditional logic in stage transitions based on request type
**Alternatives considered**:
- Separate stage enums for Service (rejected - too many stages, hard to maintain)
- Workflow engine (rejected - over-engineering)
**Rationale**: Simple conditional checks, maintains single stage enum, easy to understand

### Decision 6: Form Conditional Logic
**Chosen**: Live reactive forms in Filament using `->visible()` and `->hidden()` based on request type
**Alternatives considered**:
- Separate forms (rejected - code duplication)
- JavaScript-based hiding (rejected - not reactive, harder to maintain)
**Rationale**: Filament's built-in reactivity, clean code, type-safe

## Risks / Trade-offs

### Risk 1: Backward Compatibility
**Risk**: Existing Goods requests might break
**Mitigation**: Default all existing requests to Goods type, add migration to set default

### Risk 2: Complex Form Logic
**Risk**: Conditional form logic becomes hard to maintain
**Mitigation**: Extract form schema methods, add clear comments, write tests

### Risk 3: Performance with Child Items
**Risk**: N+1 queries when loading request with child items
**Mitigation**: Use eager loading (`with('children')`), add database indexes

### Risk 4: File Upload Size Limits
**Risk**: Large acceptance report files might cause issues
**Mitigation**: Configure PHP/Laravel upload limits, add file size validation, consider chunked uploads for future

## Migration Plan

### Phase 1: Database Schema
1. Add `request_type` column with default 'goods'
2. Add `parent_id` column (nullable)
3. Create acceptance_reports and acceptance_report_items tables
4. Add indexes

### Phase 2: Models and Enums
1. Create RequestType enum
2. Update Request model
3. Update RequestItem model
4. Create AcceptanceReport model

### Phase 3: Filament Resources
1. Update RequestResource form
2. Update ItemsRelationManager with conditional logic
3. Create AcceptanceReportResource
4. Add AcceptanceReportsRelationManager

### Phase 4: Workflow Logic
1. Update stage transition logic
2. Update relation manager visibility
3. Update validation rules

### Phase 5: Testing and Documentation
1. Write comprehensive tests
2. Update documentation
3. User acceptance testing

### Rollback Plan
- Database migrations can be rolled back
- Code changes are backward compatible (defaults to Goods)
- No data loss expected

## Open Questions
1. Should child items be editable after request moves past draft stage? (Decision: Follow same rules as main items)
2. Can a Service request have multiple acceptance reports? (Decision: Yes, for partial acceptance scenarios)
3. Should acceptance reports be required before invoicing? (Decision: Not in initial implementation, can add validation later)
4. How to handle child items in buyer/supplier quotes? (Decision: Only main items appear in quotes, child items are details)
