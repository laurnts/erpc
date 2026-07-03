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

## MODIFIED Requirements

### Requirement: Requests Entity
The system SHALL manage Requests as the atomic unit representing a single buyer inquiry from initial request through final payment. A request's items each carry their own Goods/Services type; the request derives its available workflows from the types of its items.

#### Scenario: Create a request
- **WHEN** an admin creates a request for buyer "GlobalTrade" with title "Factory Equipment Order"
- **THEN** a unique request_number is auto-generated (e.g., "REQ-2024-0001")
- **AND** the stage defaults to "new"
- **AND** base_currency defaults to system default
- **AND** no request-level Goods/Services selection is offered

#### Scenario: Add request items
- **WHEN** an admin adds item "Tyre for Toyota Prius 2020" qty 4 pcs
- **THEN** the request_item is created with description (article_id nullable)
- **AND** the item's type defaults to "goods" unless the admin selects "services"

#### Scenario: Match request item to article
- **WHEN** an admin matches "Tyre for Toyota Prius" to "Michelin Pilot Sport 215/45R17"
- **THEN** article_id is set and is_matched becomes true

#### Scenario: Combined article-supplier selection
- **WHEN** an admin creates or edits a request item
- **THEN** the form shows a single "Match to Article" dropdown (full width)
- **AND** each option shows: "[CODE] Article Name → Supplier Name ★"
- **AND** articles without suppliers show: "[CODE] Article Name"
- **AND** preferred suppliers are marked with ★

#### Scenario: Select article and supplier together
- **WHEN** an admin selects an option from the dropdown
- **THEN** both article_id and supplier_id are set from the selection
- **AND** is_matched becomes true

#### Scenario: View item assignment status
- **WHEN** viewing request items in the Items tab
- **THEN** each item shows: type badge (Goods/Services), match status (checkmark/X), article code, supplier name, quantity, unit
- **AND** a status summary shows "X/Y items matched" and "X/Y items assigned to suppliers"

#### Scenario: Clear selection
- **WHEN** an admin clears the "Match to Article" dropdown
- **THEN** both article_id and supplier_id are cleared
- **AND** is_matched becomes false

#### Scenario: Validate stage transition to sourcing
- **WHEN** stage transitions from "new" to "sourcing"
- **THEN** the transition is allowed (no prerequisites)

#### Scenario: Validate stage transition to supplier_quoting
- **WHEN** stage attempts transition to "supplier_quoting"
- **THEN** all goods items and all service main items must have article_id set
- **AND** service child items are exempt from matching
- **AND** validation fails if any non-exempt item is unmatched
