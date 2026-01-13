# ERP Shipments

Shipment tracking with item-level verification.

## ADDED Requirements

### Requirement: Shipment Tracking
The system SHALL track shipments with carrier and tracking information.

#### Scenario: Create inbound shipment
- **WHEN** admin creates shipment for supplier order PO-2024-0089-A
- **THEN** type is "inbound" (from supplier)
- **AND** shipment is linked to request and supplier_order
- **AND** status defaults to "pending"

#### Scenario: Record carrier and tracking
- **WHEN** admin records carrier "DHL" and tracking "1234567890"
- **THEN** carrier and tracking_number are stored
- **AND** tracking number is searchable

#### Scenario: Record addresses
- **WHEN** admin records origin and destination addresses
- **THEN** origin_address shows supplier location
- **AND** destination_address shows your warehouse/office

#### Scenario: Track shipment dates
- **WHEN** shipment is created
- **THEN** shipped_at records when supplier shipped
- **AND** expected_delivery shows estimated arrival
- **AND** delivered_at is set when goods received

#### Scenario: Shipment status progression
- **WHEN** shipment is created
- **THEN** status can progress: pending → in_transit → delivered → partial → failed
- **AND** "partial" indicates some items received, others pending

---

### Requirement: Shipment Items
The system SHALL track what was actually received vs what was ordered for discrepancy detection.

#### Scenario: Record shipped quantities
- **WHEN** supplier ships items
- **THEN** shipment_item links to supplier_order_item
- **AND** quantity_shipped records what supplier says they sent

#### Scenario: Record received quantities
- **WHEN** goods are received
- **THEN** quantity_received records what was actually received
- **AND** if different from shipped, discrepancy is flagged

#### Scenario: Track item condition
- **WHEN** goods are received
- **THEN** condition is set: good, damaged, or rejected
- **AND** notes can capture damage details

#### Scenario: Detect shortage
- **WHEN** quantity_received (95) is less than supplier_order_item.quantity (100)
- **THEN** shortage is flagged
- **AND** 5 units are marked as short

#### Scenario: Detect damage
- **WHEN** quantity_received is 100 but 5 are damaged
- **THEN** separate shipment_item records good (95) and damaged (5)
- **AND** each has appropriate condition set

---

### Requirement: Partial Shipments
The system SHALL support multiple shipments per supplier order for partial deliveries.

#### Scenario: First partial shipment
- **WHEN** supplier ships 50 of 100 ordered units
- **THEN** shipment is created with shipment_items for 50 units
- **AND** status is "delivered" for this shipment

#### Scenario: Second partial shipment
- **WHEN** supplier ships remaining 50 units
- **THEN** second shipment is created
- **AND** both shipments link to same supplier_order

#### Scenario: Calculate fulfillment status
- **WHEN** viewing supplier order
- **THEN** sum of shipment_items.quantity_received is compared to order quantity
- **AND** "50 of 100 received" is displayed

---

### Requirement: Outbound Shipments
The system SHALL track outbound shipments to buyers with buyer_order linkage.

#### Scenario: Create outbound shipment
- **WHEN** admin creates shipment to buyer
- **THEN** type is "outbound"
- **AND** buyer_order_id links to the buyer order
- **AND** supplier_order_id is null
- **AND** destination_address is buyer's address

#### Scenario: Outbound shipment items link to buyer order
- **WHEN** outbound shipment items are created
- **THEN** buyer_order_item_id links to specific buyer order items
- **AND** supplier_order_item_id is null
- **AND** quantity_shipped records what was sent to buyer

#### Scenario: Outbound after inbound complete
- **WHEN** all inbound shipments are delivered
- **THEN** outbound shipment can be created
- **AND** items are consolidated from all supplier deliveries
- **AND** items link back to original buyer order items

#### Scenario: Outbound shipment discrepancy
- **WHEN** quantity_received by buyer differs from quantity_shipped
- **THEN** discrepancy is recorded on shipment_item
- **AND** buyer can report short shipment or damage

#### Scenario: Partial outbound shipment
- **WHEN** only some buyer order items are shipped
- **THEN** shipment contains only those items
- **AND** additional outbound shipment can be created for remaining items
- **AND** fulfillment status shows partial

---

### Requirement: Shipment Documents
The system SHALL support uploading shipment-related documents.

#### Scenario: Upload bill of lading
- **WHEN** recording shipment
- **THEN** BOL can be uploaded with type "shipping_doc"

#### Scenario: Upload packing list
- **WHEN** goods are shipped
- **THEN** packing list can be uploaded with type "shipping_doc"

#### Scenario: Upload proof of delivery
- **WHEN** goods are delivered
- **THEN** signed POD can be uploaded with type "pod"
- **AND** POD is required to mark shipment as "delivered"

---

### Requirement: Delivery Status Overview
The system SHALL provide consolidated view of all shipments per request, including both inbound and outbound.

#### Scenario: View all shipments
- **WHEN** viewing request
- **THEN** all inbound shipments are listed with supplier reference
- **AND** all outbound shipments are listed with buyer reference
- **AND** each shows type, status, tracking, dates

#### Scenario: Inbound fulfillment progress
- **WHEN** request has 3 supplier orders
- **THEN** progress shows "2/3 suppliers delivered, 1 in transit"
- **AND** visual progress bar indicates percentage

#### Scenario: Outbound fulfillment progress
- **WHEN** request has buyer order with 10 items
- **THEN** progress shows "7/10 items shipped to buyer"
- **AND** visual progress bar indicates percentage

#### Scenario: Overdue shipment alert
- **WHEN** expected_delivery has passed without delivery
- **THEN** shipment is highlighted as overdue
- **AND** appears in dashboard alerts
- **AND** applies to both inbound and outbound shipments
