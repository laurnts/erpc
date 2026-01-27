## ADDED Requirements

### Requirement: Unit of Measure Management
The system SHALL provide a centralized management system for units of measure (e.g., "pcs", "kg", "meter", "box") that can be configured per team and used consistently across all forms.

#### Scenario: Create a new unit of measure
- **WHEN** an admin creates a new unit with code "pcs" and label "Pieces"
- **THEN** the unit is created and scoped to the current team
- **AND** the unit code must be unique per team
- **AND** the unit is available for selection in all forms

#### Scenario: List available units
- **WHEN** a user views the unit of measure list
- **THEN** active units are displayed with code, label, and active status
- **AND** units are sorted by sort_order, then by code

#### Scenario: Edit a unit of measure
- **WHEN** an admin edits a unit's label from "Pieces" to "Pieces (Each)"
- **THEN** the label is updated
- **AND** existing records using this unit display the new label

#### Scenario: Deactivate a unit
- **WHEN** an admin deactivates a unit
- **THEN** the unit is no longer available for new selections
- **AND** existing records retain their unit assignment
- **AND** the unit cannot be deleted (only deactivated)

#### Scenario: Default units seeded
- **WHEN** team ERP is initialized or UnitOfMeasureResource is first accessed
- **THEN** default units are created: pcs (Pieces), kg (Kilograms), mt (Metric Tons), set (Sets), box (Boxes), roll (Rolls), pair (Pairs), l (Liters), m (Meters)
- **AND** seeding is idempotent (doesn't create duplicates)

#### Scenario: Use unit in article form
- **WHEN** an admin creates or edits an article
- **THEN** the unit field is a dropdown showing active units for the team
- **AND** the unit can be searched by code or label
- **AND** default unit is "pcs" if not specified

#### Scenario: Use unit in request item form
- **WHEN** an admin adds an item to a request
- **THEN** the unit field is a dropdown showing active units
- **AND** the selected unit is stored as a foreign key relationship

#### Scenario: Use unit in quote/order forms
- **WHEN** an admin creates buyer quotes, supplier quotes, or orders
- **THEN** unit fields in item forms are dropdowns showing active units
- **AND** units are consistent across all quote and order forms

#### Scenario: Display unit in tables
- **WHEN** viewing articles, request items, quotes, or orders
- **THEN** unit columns display the unit label (e.g., "Pieces" not "pcs")
- **AND** unit information is loaded efficiently via relationships

#### Scenario: Unit scoping
- **WHEN** a user selects a unit in any form
- **THEN** only units belonging to the current team are shown
- **AND** units from other teams are not accessible

#### Scenario: Display unit in PDF exports
- **WHEN** generating PDFs for orders, quotes, or invoices
- **THEN** unit columns display the unit label (e.g., "Pieces" not "pcs")
- **AND** unit information is loaded from UnitOfMeasure relationship
- **AND** legacy units without UnitOfMeasure still display correctly

#### Scenario: Create order from quote
- **WHEN** creating a buyer order from a buyer quote
- **THEN** unit_of_measure_id is copied from quote item to order item
- **AND** unit field is set from UnitOfMeasure code
- **AND** unit display shows the unit label

#### Scenario: Create invoice from order
- **WHEN** creating a buyer invoice from a buyer order
- **THEN** unit_of_measure_id is copied from order item to invoice item
- **AND** unit field is set from UnitOfMeasure code
- **AND** unit display shows the unit label

#### Scenario: Shipment item unit display
- **WHEN** viewing or creating inbound shipments
- **THEN** shipment items display unit from associated order item
- **AND** unit label is shown (not code)
- **AND** unit dropdown shows unit labels when selecting order items

#### Scenario: Default unit per team
- **WHEN** a team has a default unit set
- **THEN** only one unit can be marked as default per team
- **AND** setting a new default unit automatically unsets the previous default
- **AND** default unit can be used as a fallback in forms

## Implementation Notes

### Accessors Added
- All item models now have `getUnitLabelAttribute()` accessor that:
  - Returns `unitOfMeasure->label` if relationship exists
  - Falls back to Unit enum value if available
  - Falls back to raw unit string
  - Returns '—' if no unit is found

### Observers Updated
- BuyerQuoteItemObserver: Syncs `unit` field from `unit_of_measure_id` on create/update
- SupplierQuoteItemObserver: Syncs `unit` field from `unit_of_measure_id` on create/update
- UnitOfMeasureObserver: Handles team_id, creator_id, and ensures only one default per team

### Creation Methods Updated
- BuyerOrderItem::createFromQuoteItem(): Copies unit_of_measure_id and sets unit using setRawAttributes
- SupplierOrder::createFromQuote(): Copies unit_of_measure_id and sets unit using setRawAttributes
- BuyerInvoiceItem::createFromOrderItem(): Copies unit_of_measure_id and sets unit
- SupplierOrdersRelationManager: Sets unit when creating items from buyer orders

### PDF Templates Updated
- All PDF templates now use `unit_label` accessor instead of `unit` field
- Ensures consistent display of unit labels across all documents

### Shipment Integration
- ShipmentItem::getUnit() updated to use `unit_label` accessor from order items
- ShipmentsRelationManager updated to display unit labels in dropdowns
