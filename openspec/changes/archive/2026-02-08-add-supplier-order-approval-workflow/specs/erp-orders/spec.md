## MODIFIED Requirements

### Requirement: Supplier Orders
The system SHALL create multiple supplier orders per request, one for each selected supplier, and require dual approval before sending to suppliers.

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
- **AND** status can progress: draft → confirmed → approved → sent → shipped → delivered → cancelled
- **AND** confirmed_at is recorded when status becomes "confirmed"
- **AND** approved_at is recorded when status becomes "approved" (after 2 approvals)

#### Scenario: Track expected delivery
- **WHEN** supplier order has expected_delivery date
- **THEN** date is used for shipment tracking
- **AND** actual_delivery is recorded when items received

#### Scenario: Create supplier order from buyer order
- **WHEN** admin creates supplier orders from confirmed buyer order
- **THEN** supplier orders are created in draft status
- **AND** "Confirm and send to suppliers" option is not available
- **AND** orders must be confirmed separately before approval workflow begins

#### Scenario: Approval workflow requires dual approval
- **WHEN** supplier order status becomes "confirmed"
- **THEN** email notifications are sent to team members with roles: Dept Head of Sales, Deputy Director, and Director
- **AND** supplier order appears in "Supplier Orders" list under Approval menu
- **AND** approval requires minimum 2 approvals from the 3 eligible roles
- **AND** same user cannot approve twice
- **AND** status changes to "approved" after second approval
- **AND** order remains visible in approval list after approval (shows APPROVED status with approver names)
- **AND** order disappears from approval list only when status becomes SENT

#### Scenario: Approve from Approval menu
- **WHEN** approver navigates to Approval > Supplier Orders
- **THEN** list shows only CONFIRMED orders needing approval
- **AND** approver can approve directly from list or view order details first
- **AND** approve action is only visible to users with approval roles
- **AND** approve action is hidden if user already approved the order

#### Scenario: Send button only after approval
- **WHEN** supplier order status is "approved"
- **THEN** "Send" and "View PDF" buttons appear in action group
- **AND** clicking "Send" changes status to "sent"
- **AND** purchase order email is sent to supplier

#### Scenario: PDF shows approval section
- **WHEN** supplier order status is "approved"
- **THEN** PDF displays 4-column approval section at bottom:
  - Column 1: "Checked by" shows key account name(s) assigned to buyer
  - Column 2: "Approved by" shows first approver name
  - Column 3: "Approved by" shows second approver name
  - Column 4: "Supplier/Vendor" shows empty signature line

---

## ADDED Requirements

### Requirement: Supplier Order Approval Tracking
The system SHALL track which team members approved supplier orders and when approvals occurred.

#### Scenario: Track first approval
- **WHEN** eligible approver clicks "Approve" button
- **THEN** approver_1_id is set to approver's user ID
- **AND** approver_1 timestamp is recorded
- **AND** status remains "confirmed" until second approval

#### Scenario: Track second approval
- **WHEN** second eligible approver (different from first) clicks "Approve" button
- **THEN** approver_2_id is set to approver's user ID
- **AND** approved_at timestamp is recorded
- **AND** status automatically changes to "approved"

#### Scenario: Prevent duplicate approval
- **WHEN** user who already approved attempts to approve again
- **THEN** approval action is blocked
- **AND** error message indicates user already approved

#### Scenario: Role-based approval access
- **WHEN** user views Approval > Supplier Orders list
- **THEN** list shows orders where:
  - Status is "approved" (all approved orders remain visible) OR
  - Status is "confirmed" AND order needs approval (approver_1_id is null OR approver_2_id is null)
  - User has role: Dept Head of Sales, Deputy Director, or Director
- **AND** approver names are displayed in "Approver 1" and "Approver 2" columns
- **AND** approve action is hidden if user already approved the order
- **AND** approve action is not available in Request page supplier orders relation manager
- **AND** navigation menu item is visible only to users with approval roles or administrators

#### Scenario: Tax calculation for taxable suppliers
- **WHEN** supplier order is created for a taxable supplier
- **THEN** line totals automatically include tax (unit_price × quantity × (1 + tax_rate/100))
- **AND** tax_amount is calculated per unit (unit_price × tax_rate / 100)
- **AND** tax_total in summary section updates reactively as items are added/modified
- **AND** tax_total is calculated as sum of (quantity × tax_amount) for all items
- **AND** totals are automatically recalculated when items are saved

#### Scenario: PDF and Email tax display
- **WHEN** supplier order PDF or email is generated
- **THEN** item table displays:
  - Unit Price column: price excluding tax (unit_price_exc_tax)
  - Tax column: tax amount for the line (tax_amount × quantity)
  - Total column: line total including tax (line_total)
- **AND** all monetary values use currency formatting

