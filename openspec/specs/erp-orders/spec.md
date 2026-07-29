# erp-orders Specification

## Purpose
TBD - created by archiving change add-erp-trading-system. Update Purpose after archive.
## Requirements
### Requirement: Buyer Orders
The system SHALL create consolidated buyer orders from accepted quotes, with locked values.

#### Scenario: Create buyer order from quote
- **WHEN** admin creates buyer order from accepted quote Q-2024-0089-v2
- **THEN** order_number is generated (e.g., "BO-2024-0089")
- **AND** all items from quote are copied to order
- **AND** payment terms are copied and locked

#### Scenario: One buyer order per request
- **WHEN** a buyer order exists for a request
- **THEN** no additional buyer orders can be created
- **AND** unique constraint on request_id enforced

#### Scenario: Lock values on order
- **WHEN** buyer order is created
- **THEN** unit_price, unit_price_exc_tax, quantity, tax_code_id, tax_rate, is_tax_inclusive, payment terms are immutable
- **AND** editing these fields is blocked by system
- **AND** item sort_order is also locked

#### Scenario: Record buyer PO number
- **WHEN** buyer provides their PO reference "GT-PO-2024-445"
- **THEN** po_number is stored on the order
- **AND** received_at is set to date PO was received

#### Scenario: Order status tracking
- **WHEN** order is created
- **THEN** status defaults to "confirmed"
- **AND** status can progress: confirmed → in_progress → fulfilled → cancelled

#### Scenario: Credit limit check
- **WHEN** creating buyer order
- **THEN** system checks if order total exceeds available credit
- **AND** warning is displayed if credit limit exceeded
- **AND** order can still be created with warning acknowledgment

---

### Requirement: Supplier Orders
The system SHALL create multiple supplier orders per request, one for each selected supplier.

#### Scenario: Generate supplier orders
- **WHEN** buyer order is confirmed with items from 3 suppliers
- **THEN** 3 supplier orders are created (one per supplier)
- **AND** po_number follows pattern PO-2024-0089-A, PO-2024-0089-B, PO-2024-0089-C

#### Scenario: Supplier order from selected quote
- **WHEN** supplier order is created for MotorCorp
- **THEN** it links to the selected supplier_quote
- **AND** items are copied from supplier_quote_items

#### Scenario: Lock exchange rate on supplier order
- **WHEN** supplier order is created
- **THEN** exchange_rate_to_base is captured from current rate
- **AND** total_in_base is calculated and locked

#### Scenario: Supplier order status tracking
- **WHEN** supplier order is created
- **THEN** status defaults to "draft"
- **AND** status can progress: draft → sent → confirmed → shipped → delivered → cancelled
- **AND** sent_at is recorded when status becomes "sent"

#### Scenario: Track expected delivery
- **WHEN** supplier order has expected_delivery date
- **THEN** date is used for shipment tracking
- **AND** actual_delivery is recorded when items received

---

### Requirement: Order Item Management
The system SHALL track order items with supplier attribution on buyer orders.

#### Scenario: Buyer order items with supplier reference
- **WHEN** buyer order item is created
- **THEN** supplier_id indicates which supplier fulfills this item
- **AND** this is internal reference (not shown to buyer)
- **AND** sort_order is preserved from quote

#### Scenario: Buyer order item tax snapshot
- **WHEN** buyer order item is created from quote
- **THEN** tax_code_id, is_tax_inclusive, tax_rate, unit_price_exc_tax are copied
- **AND** all tax values are locked (immutable)
- **AND** original tax calculation is preserved for accounting

#### Scenario: Supplier order items match quote
- **WHEN** supplier order items are created
- **THEN** they match the supplier_quote_items exactly
- **AND** prices and tax values are locked from quote values
- **AND** sort_order is preserved from quote

#### Scenario: Supplier order item tax snapshot
- **WHEN** supplier order item is created from quote
- **THEN** tax_code_id, is_tax_inclusive, tax_rate, unit_price_exc_tax are copied
- **AND** all tax values are locked (immutable)

---

### Requirement: Value Locking
The system SHALL prevent modification of financial values once quotes become orders.

#### Scenario: Quote values are editable
- **WHEN** editing a buyer quote in draft or sent status
- **THEN** unit_price, quantity, cost_price are editable
- **AND** exchange rates can be updated

#### Scenario: Order values are locked
- **WHEN** attempting to edit buyer order item prices
- **THEN** modification is blocked
- **AND** error message indicates values are locked

#### Scenario: Order amendments require new documents
- **WHEN** order changes are needed after creation
- **THEN** credit notes or revised POs must be created
- **AND** original order values remain unchanged

---

### Requirement: Delivery Overview
The system SHALL provide overview of delivery status across all supplier orders.

#### Scenario: Delivery status summary
- **WHEN** viewing request orders tab
- **THEN** summary shows "2/3 supplier orders delivered, 1 in transit"
- **AND** progress bar indicates overall fulfillment percentage

#### Scenario: Expected delivery tracking
- **WHEN** supplier orders have expected_delivery dates
- **THEN** consolidated view shows all expected dates
- **AND** overdue deliveries are highlighted

### Requirement: Buyer Order Delete Protection
The system SHALL enforce database-level `RESTRICT` (not `CASCADE`) on `buyer_orders.request_id` →
`requests` and `buyer_orders.buyer_id` → `companies`. A request or buyer company that has a buyer
order SHALL NOT be hard-deletable; it must be archived (soft-deleted) instead. The `buyer_orders.team_id`
foreign key is unaffected and remains `cascadeOnDelete()`.

#### Scenario: Force-deleting a request with a buyer order is rejected
- **WHEN** a request that has a `BuyerOrder` is force-deleted
- **THEN** the database raises a foreign key violation and the request row is not removed

#### Scenario: Force-deleting a buyer company with a buyer order is rejected
- **WHEN** a buyer company that has a `BuyerOrder` is force-deleted
- **THEN** the database raises a foreign key violation and the company row is not removed

### Requirement: Document Number Allocation
Buyer order (`order_number`) and supplier order (`po_number`) numbers SHALL be allocated from a locked counter row (one row per team, document key, and calendar year in `document_number_sequences`) rather than by reading the highest existing number, so that concurrent creates cannot receive the same number and the sequence does not regress once a team's yearly count passes 9999 (a string-sorted "highest number" query would otherwise rank `'9999'` above `'10000'`). Only the base PO number for a request consumes the counter; the `-A`/`-B`/`-C` suffix issued to the second and later supplier orders on the same request reuses the first order's base number and does not allocate a new sequence value.

#### Scenario: Concurrent order creates do not collide
- **WHEN** two buyer orders are created for the same team in the same year at effectively the same time
- **THEN** each receives a distinct, correctly incrementing `order_number`
- **AND** neither create fails due to a duplicate-number save

#### Scenario: Sequence does not regress past 9999
- **WHEN** a team's supplier order count for a year is already at 9999
- **THEN** the next allocated base `po_number` sequence value is 10000, not a value already issued

#### Scenario: Suffixed supplier orders on the same request do not consume additional sequence values
- **WHEN** a second and third supplier order are created for a request that already has a supplier order `PO-2026-0042`
- **THEN** they receive `PO-2026-0042-A` and `PO-2026-0042-B`
- **AND** the team's `supplier_order` counter for 2026 is unaffected by these two additional orders

