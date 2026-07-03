# erp-trading-core Specification Delta

## ADDED Requirements

### Requirement: Request Type Classification
The system SHALL support two types of requests: Goods and Service, with different workflows for each type.

#### Scenario: Create Goods request
- **WHEN** a user creates a new request and selects "Goods" as the request type
- **THEN** the request follows the standard Goods workflow (with quotation evaluation and inbound shipments)
- **AND** all request items must be matched to articles before proceeding

#### Scenario: Create Service request
- **WHEN** a user creates a new request and selects "Service" as the request type
- **THEN** the request follows the Service workflow (no quotation evaluation, acceptance reports instead of inbound shipments)
- **AND** only main items must be matched to articles (child items are optional)

#### Scenario: Request type defaults to Goods
- **WHEN** a user creates a new request without specifying request type
- **THEN** the request type defaults to "Goods"
- **AND** existing requests without request type are treated as Goods requests

---

### Requirement: Service Request Child Items
The system SHALL support hierarchical item structure for Service requests, where main items can have child items.

#### Scenario: Create main item with child items
- **WHEN** a user creates a Service request item and matches it to an article
- **THEN** a "Child Items" section appears below "Match to Article"
- **AND** the user can add multiple child items with description, quantity, and unit of measure

#### Scenario: Child items structure
- **WHEN** a main item has child items
- **THEN** child items are stored with parent_id pointing to the main item
- **AND** child items do not require article matching
- **AND** child items are displayed indented or with a badge in the items table

#### Scenario: Main item from article
- **WHEN** a Service request item is matched to an article
- **THEN** that item becomes the main item
- **AND** child items can be added to provide detail breakdown of the service

#### Scenario: Example hierarchical structure
- **WHEN** main item is "Preparation work" matched to article "PREP-001"
- **AND** child items are:
  - "Mobilisation and Demobilisation of work and work tools" (qty: 1, unit: set)
  - "Project signs" (qty: 5, unit: pcs)
- **THEN** the structure is stored with parent-child relationships
- **AND** only the main item appears in supplier quotes

---

### Requirement: Acceptance Reports for Service Requests
The system SHALL provide acceptance reports for Service requests, replacing the inbound shipment workflow used for Goods requests.

#### Scenario: Create acceptance report
- **WHEN** a user creates an acceptance report for a Service request
- **THEN** a report_number is auto-generated (e.g., "AR-2026-0001")
- **AND** the report includes: reported date, reported by user, notes, and file uploads
- **AND** the report can be linked to specific request items

#### Scenario: Upload acceptance report files
- **WHEN** a user uploads files to an acceptance report
- **THEN** the system accepts PDF, Word (.doc, .docx), and image files (.jpg, .jpeg, .png, .gif)
- **AND** files are stored using Spatie Media Library
- **AND** files can be previewed and downloaded from the acceptance report view

#### Scenario: Multiple acceptance reports per request
- **WHEN** a Service request has multiple acceptance reports
- **THEN** all reports are listed in the Acceptance Reports tab
- **AND** each report can be viewed, edited, or deleted independently

#### Scenario: Acceptance reports replace inbound shipments
- **WHEN** a Service request progresses through workflow stages
- **THEN** the system does not show inbound shipment tracking
- **AND** acceptance reports are used instead to track service completion

---

## MODIFIED Requirements

### Requirement: Requests Entity
The system SHALL manage Requests as the atomic unit representing a single buyer inquiry from initial request through final payment. Requests can be classified as Goods or Service type, with different workflows for each.

#### Scenario: Create a request
- **WHEN** an admin creates a request for buyer "GlobalTrade" with title "Factory Equipment Order"
- **THEN** a unique request_number is auto-generated (e.g., "REQ-2024-0001")
- **AND** the stage defaults to "draft"
- **AND** request_type defaults to "goods"
- **AND** base_currency defaults to system default

#### Scenario: Create Service request
- **WHEN** an admin creates a request and selects request_type "Service"
- **THEN** the request follows Service workflow rules
- **AND** child items can be added to main items
- **AND** acceptance reports replace inbound shipments

#### Scenario: Add request items
- **WHEN** an admin adds item "Tyre for Toyota Prius 2020" qty 4 pcs
- **THEN** the request_item is created with description (article_id nullable)
- **AND** if request_type is "Service", child items section appears after matching to article

#### Scenario: Match request item to article
- **WHEN** an admin matches "Tyre for Toyota Prius" to "Michelin Pilot Sport 215/45R17"
- **THEN** article_id is set and is_matched becomes true
- **AND** if request_type is "Service", this item becomes a main item and child items can be added

#### Scenario: Combined article-supplier selection
- **WHEN** an admin creates or edits a request item
- **THEN** the form shows a single "Match to Article" dropdown (full width)
- **AND** each option shows: "[CODE] Article Name → Supplier Name ★"
- **AND** articles without suppliers show: "[CODE] Article Name"
- **AND** preferred suppliers are marked with ★
- **AND** if request_type is "Service", child items section appears below

#### Scenario: Select article and supplier together
- **WHEN** an admin selects an option from the dropdown
- **THEN** both article_id and supplier_id are set from the selection
- **AND** is_matched becomes true
- **AND** if request_type is "Service", the item becomes a main item

#### Scenario: View item assignment status
- **WHEN** viewing request items in the Items tab
- **THEN** each item shows: match status (checkmark/X), article code, supplier name, quantity, unit
- **AND** child items (for Service requests) are displayed with indentation or badge
- **AND** a status summary shows "X/Y items matched" and "X/Y items assigned to suppliers"

#### Scenario: Clear selection
- **WHEN** an admin clears the "Match to Article" dropdown
- **THEN** both article_id and supplier_id are cleared
- **AND** is_matched becomes false
- **AND** if request_type is "Service", child items are also cleared

#### Scenario: Validate stage transition to sourcing
- **WHEN** stage transitions from "draft" to "awaiting_supplier_response"
- **THEN** the transition is allowed (no prerequisites)
- **AND** for Service requests, only main items need to be matched

#### Scenario: Validate stage transition to supplier_quoting
- **WHEN** stage attempts transition to "preparing_buyer_quote"
- **THEN** for Goods requests: all request items must have article_id set
- **AND** for Service requests: all main items must have article_id set (child items optional)
- **AND** validation fails if required items are unmatched

---

### Requirement: Request Stage Lifecycle
The system SHALL enforce a stage-based workflow for requests with defined transitions. The workflow differs based on request type (Goods vs Service).

#### Scenario: Valid stage progression for Goods request
- **WHEN** Goods request progresses through stages: draft → awaiting_supplier_response → preparing_buyer_quote → awaiting_buyer_confirmation → preparing_supplier_order → awaiting_shipment → shipped → delivered → invoiced → paid → completed
- **THEN** each transition is valid and recorded
- **AND** quotation evaluation can be created
- **AND** inbound shipments are tracked

#### Scenario: Valid stage progression for Service request
- **WHEN** Service request progresses through stages: draft → awaiting_supplier_response → preparing_buyer_quote → awaiting_buyer_confirmation → preparing_supplier_order → (acceptance reports) → invoiced → paid → completed
- **THEN** each transition is valid and recorded
- **AND** quotation evaluation cannot be created
- **AND** inbound shipments are not tracked (replaced by acceptance reports)

#### Scenario: Stage determines available actions
- **WHEN** request is in stage "draft"
- **THEN** user can add/edit items but cannot create supplier quotes
- **AND** for Service requests, child items can be added to main items

#### Scenario: Stage closed marks completion
- **WHEN** request stage is set to "completed"
- **THEN** closed_at timestamp is recorded
- **AND** the request is considered complete

#### Scenario: Stage cancelled marks termination
- **WHEN** request stage is set to "cancelled"
- **THEN** the request is marked as terminated
- **AND** no further actions are allowed

---

## ADDED Requirements

### Requirement: Conditional Form Logic Based on Request Type
The system SHALL display different form fields and sections based on the selected request type.

#### Scenario: Goods request form
- **WHEN** request_type is "Goods"
- **THEN** the RequestItem form shows standard fields: description, quantity, unit, match to article, notes
- **AND** no child items section is displayed
- **AND** all items require article matching

#### Scenario: Service request form
- **WHEN** request_type is "Service"
- **THEN** the RequestItem form shows: description, quantity, unit, match to article
- **AND** after matching to article, a "Child Items" section appears
- **AND** child items can be added with description, quantity, and unit
- **AND** only main items require article matching

#### Scenario: Request type selection
- **WHEN** creating or editing a request
- **THEN** a "Request Type" select field appears in the Request Details section
- **AND** options are "Goods" and "Service"
- **AND** default is "Goods"
- **AND** the selection is required

---

### Requirement: Workflow Differences for Service Requests
The system SHALL apply different workflow rules for Service requests compared to Goods requests.

#### Scenario: No quotation evaluation for Service requests
- **WHEN** a Service request is in stage "preparing_buyer_quote"
- **THEN** the Quotation Evaluations tab is hidden or disabled
- **AND** users cannot create quotation evaluation documents

#### Scenario: No inbound shipments for Service requests
- **WHEN** a Service request progresses past "preparing_supplier_order"
- **THEN** the Shipments tab shows only outbound shipments (if any)
- **AND** inbound shipment tracking is not available
- **AND** acceptance reports are used instead

#### Scenario: Acceptance reports tab visibility
- **WHEN** viewing a Service request
- **THEN** an "Acceptance Reports" tab is visible
- **AND** users can create, view, and manage acceptance reports

#### Scenario: Send to suppliers for Service requests
- **WHEN** sending Service request items to suppliers
- **THEN** only main items are sent (child items are not included in quotes)
- **AND** child items remain as detail breakdown for internal reference
