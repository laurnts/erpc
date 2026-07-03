# erp-trading-core Specification Delta

## ADDED Requirements

### Requirement: Item Type Classification
The system SHALL classify each request item as Goods or Services, with the item's type determining its fulfillment channel, hierarchy support, and quoting behavior. Requests SHALL NOT carry a type of their own.

#### Scenario: Item type defaults to Goods
- **WHEN** a user adds an item to a request without choosing a type
- **THEN** the item's type is "goods"

#### Scenario: Create a mixed request
- **WHEN** a user creates a request with item "Industrial compressor" (goods) and item "Installation and commissioning" (services)
- **THEN** both items belong to the same request
- **AND** no request-level type selection is required or offered

#### Scenario: Service item supports child items
- **WHEN** an item's type is "services" and it is matched to an article
- **THEN** the user can add child items (description, quantity, unit of measure) under it
- **AND** child items inherit the parent item's type
- **AND** child items do not require article matching

#### Scenario: Goods item has no child items
- **WHEN** an item's type is "goods"
- **THEN** no child-items section is offered for that item

#### Scenario: Existing requests migrate to item-level types
- **WHEN** the migration runs on a request previously typed "services" with three items
- **THEN** all three items become type "services"
- **AND** the request-level type field is removed

---

### Requirement: Item-Level Fulfillment Completion
The system SHALL consider a request's fulfillment complete when every item is satisfied through its own channel: goods items via received shipments, service main items via acceptance reports.

#### Scenario: Mixed request completes through both channels
- **WHEN** a request has one goods item fully received via an inbound shipment
- **AND** one service main item included on an acceptance report
- **THEN** the request's fulfillment is complete

#### Scenario: Mixed request incomplete when one channel lags
- **WHEN** the goods item is fully received but no acceptance report covers the service main item
- **THEN** the request's fulfillment is not complete
- **AND** the delivery/completion overview shows the service item as outstanding

#### Scenario: Service child items do not gate completion
- **WHEN** a service main item is covered by an acceptance report
- **THEN** its child items are considered covered with it

---

### Requirement: Acceptance Reports
The system SHALL provide acceptance reports as the fulfillment record for services items, available on any request that has at least one services item.

#### Scenario: Create acceptance report
- **WHEN** a user creates an acceptance report for a request with services items
- **THEN** a report_number is auto-generated (e.g., "AR-2026-0001")
- **AND** the report includes: reported date, reported by user, notes, and file uploads
- **AND** the report links to specific services request items (goods items are not offered)

#### Scenario: Upload acceptance report files
- **WHEN** a user uploads files to an acceptance report
- **THEN** the system accepts PDF, Word (.doc, .docx), and image files (.jpg, .jpeg, .png, .gif)
- **AND** files are stored using Spatie Media Library
- **AND** files can be previewed and downloaded from the acceptance report view

#### Scenario: Multiple acceptance reports per request
- **WHEN** a request has multiple acceptance reports
- **THEN** all reports are listed in the Acceptance Reports tab
- **AND** each report can be viewed, edited, or deleted independently

#### Scenario: Acceptance Reports tab visibility
- **WHEN** a request has at least one services item
- **THEN** the Acceptance Reports tab is visible (alongside the shipments tab when goods items also exist)
- **AND** the tab is hidden when the request has no services items

## MODIFIED Requirements

### Requirement: Requests Entity
The system SHALL manage Requests as the atomic unit representing a single buyer inquiry from initial request through final payment. Each request item carries its own Goods/Services type; the request derives its available workflows from the types of its items.

#### Scenario: Create a request
- **WHEN** an admin creates a request for buyer "GlobalTrade" with title "Factory Equipment Order"
- **THEN** a unique request_number is auto-generated (e.g., "REQ-2024-0001")
- **AND** the stage defaults to "draft"
- **AND** base_currency defaults to system default
- **AND** no request-level Goods/Services selection is offered

#### Scenario: Create a mixed request
- **WHEN** an admin adds a goods item and a services item to the same request
- **THEN** both items coexist on the request
- **AND** child items can be added to services main items
- **AND** shipments and acceptance reports are both available

#### Scenario: Add request items
- **WHEN** an admin adds item "Tyre for Toyota Prius 2020" qty 4 pcs
- **THEN** the request_item is created with description (article_id nullable)
- **AND** the item's type defaults to "goods" unless the admin selects "services"
- **AND** if the item's type is "services", a child items section appears after matching to article

#### Scenario: Match request item to article
- **WHEN** an admin matches "Tyre for Toyota Prius" to "Michelin Pilot Sport 215/45R17"
- **THEN** article_id is set and is_matched becomes true
- **AND** if the item's type is "services", this item becomes a main item and child items can be added

#### Scenario: Combined article-supplier selection
- **WHEN** an admin creates or edits a request item
- **THEN** the form shows a single "Match to Article" dropdown (full width)
- **AND** each option shows: "[CODE] Article Name → Supplier Name ★"
- **AND** articles without suppliers show: "[CODE] Article Name"
- **AND** preferred suppliers are marked with ★
- **AND** if the item's type is "services", the child items section appears below

#### Scenario: Select article and supplier together
- **WHEN** an admin selects an option from the dropdown
- **THEN** both article_id and supplier_id are set from the selection
- **AND** is_matched becomes true
- **AND** if the item's type is "services", the item becomes a main item

#### Scenario: View item assignment status
- **WHEN** viewing request items in the Items tab
- **THEN** each item shows: type badge (Goods/Services), match status (checkmark/X), article code, supplier name, quantity, unit
- **AND** child items of services main items are displayed with indentation or badge
- **AND** a status summary shows "X/Y items matched" and "X/Y items assigned to suppliers"

#### Scenario: Clear selection
- **WHEN** an admin clears the "Match to Article" dropdown
- **THEN** both article_id and supplier_id are cleared
- **AND** is_matched becomes false
- **AND** if the item's type is "services", child items are also cleared

#### Scenario: Validate stage transition to sourcing
- **WHEN** stage transitions from "draft" to "awaiting_supplier_response"
- **THEN** the transition is allowed (no prerequisites)

#### Scenario: Validate stage transition to supplier_quoting
- **WHEN** stage attempts transition to "preparing_buyer_quote"
- **THEN** all goods items and all services main items must have article_id set
- **AND** services child items are exempt from matching
- **AND** validation fails if any non-exempt item is unmatched

---

### Requirement: Request Stage Lifecycle
The system SHALL enforce a stage-based workflow for requests with defined transitions. A single stage progression applies to all requests; fulfillment-stage behavior derives from the types of the request's items.

#### Scenario: Valid stage progression
- **WHEN** a request progresses through stages: draft → awaiting_supplier_response → preparing_buyer_quote → awaiting_buyer_confirmation → preparing_supplier_order → fulfillment stages → invoiced → paid → completed
- **THEN** each transition is valid and recorded
- **AND** shipment tracking applies to the request's goods items
- **AND** acceptance reports apply to the request's services items

#### Scenario: Stage determines available actions
- **WHEN** request is in stage "draft"
- **THEN** user can add/edit items but cannot create supplier quotes
- **AND** child items can be added to services main items

#### Scenario: Stage closed marks completion
- **WHEN** request stage is set to "completed"
- **THEN** closed_at timestamp is recorded
- **AND** the request is considered complete

#### Scenario: Stage cancelled marks termination
- **WHEN** request stage is set to "cancelled"
- **THEN** the request is marked as terminated
- **AND** no further actions are allowed

## REMOVED Requirements

### Requirement: Conditional Form Logic Based on Request Type
**Reason**: Superseded by item-level form logic — the child-items section and matching rules now key off each item's own type (see Item Type Classification); there is no request-level type to condition on.
**Migration**: Form behavior is preserved per item: services items show the child-items section after article matching; goods items never do.

### Requirement: Workflow Differences for Service Requests
**Reason**: Superseded by item-level fulfillment — shipment eligibility, acceptance-report eligibility, quotation evaluation scope, and supplier-quote composition are now derived from the presence and type of individual items (see Item-Level Fulfillment Completion here, Shipment Eligibility by Item Type in erp-shipments, and Quotation Evaluation Item Scope / Item-Type-Driven Quote Composition in erp-quoting).
**Migration**: Existing service requests behave identically: all their items become services items, so QE stays unavailable, shipments stay hidden, and acceptance reports remain the fulfillment channel.
