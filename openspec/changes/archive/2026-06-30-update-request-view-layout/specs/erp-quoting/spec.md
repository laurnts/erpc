## ADDED Requirements

### Requirement: Request View Page Summary
The Request view page SHALL display a summary section with three columns: Financial Summary, Payment Terms, and Shipment information.

#### Scenario: Three-column layout display
- **WHEN** a user views a Request detail page
- **THEN** the summary area displays three columns side-by-side
- **AND** the first column shows Financial Summary (Buyer Total, Supplier Costs, Gross Margin, Margin %)
- **AND** the second column shows Payment Terms section
- **AND** the third column shows Shipment section

#### Scenario: Payment Terms section display
- **WHEN** a Request has an associated BuyerOrder with BuyerQuote
- **THEN** the Payment Terms section displays:
  - **AND** Prepayment value formatted as percentage (e.g., "10%") if prepayment_type is PERCENT
  - **AND** Prepayment value formatted as currency (e.g., "Rp 1,000,000") if prepayment_type is FIXED
  - **AND** List of payment terms showing due_days and percentage for each term
  - **AND** Payment status (Paid/Not Paid) for each payment term based on BuyerInvoice payment records

#### Scenario: Payment term status calculation
- **WHEN** a payment term has associated BuyerInvoice records with payments
- **AND** the total paid amount equals or exceeds the payment term amount
- **THEN** the status displays as "Paid"
- **WHEN** a payment term has no payments or partial payments
- **THEN** the status displays as "Not Paid"

#### Scenario: Shipment section display
- **WHEN** a Request has associated Shipment records
- **THEN** the Shipment section displays a list of shipments
- **AND** each shipment shows:
  - Shipment number (shipment_number)
  - Status badge (from ShipmentStatus enum)
  - Carrier name (carrier_name or "-" if not set)
  - Tracking number (tracking_number or "-" if not set)

#### Scenario: Empty state handling
- **WHEN** a Request has no BuyerOrder or BuyerQuote
- **THEN** Payment Terms section shows empty state or placeholder
- **WHEN** a Request has no Shipments
- **THEN** Shipment section shows empty state or placeholder
