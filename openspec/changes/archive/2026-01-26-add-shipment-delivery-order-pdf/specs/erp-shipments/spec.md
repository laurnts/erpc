## ADDED Requirements

### Requirement: Delivery Order PDF Generation
The system SHALL provide PDF generation for inbound shipments with Delivery Order (DO) document format.

#### Scenario: Generate DO PDF from shipment
- **WHEN** user clicks PDF button on an inbound shipment row
- **THEN** a Delivery Order PDF is generated and downloaded
- **AND** PDF filename format is `DO_{do_number}.pdf`
- **AND** DO number is auto-generated if not already set

#### Scenario: DO number format
- **WHEN** DO number is generated for a shipment
- **THEN** format is `{4digit_increment}-CP/DO/{roman_month}/{year}`
- **AND** increment number is sequential per team/month/year
- **AND** month is represented in Roman numerals (I-XII)
- **AND** year is current year (4 digits)
- **EXAMPLE**: `0001-CP/DO/I/2025` for first DO in January 2025

#### Scenario: PDF content includes shipment details
- **WHEN** DO PDF is generated
- **THEN** PDF includes:
  - DO number (header)
  - Current date (when PDF generated)
  - PO number from associated supplier order
  - Buyer name from request's buyer company
  - Item table with shipment items
  - Delivery address from buyer company
  - Central purchasing signature section
- **AND** PDF is generated in A4 landscape orientation

#### Scenario: Item table columns
- **WHEN** DO PDF is generated
- **THEN** item table includes columns:
  - Number (sequential: 1, 2, 3...)
  - Item Name (from supplier order item description)
  - Brand (from article, if available)
  - Model (from article, if available)
  - Qty (quantity shipped from shipment item)
  - Remarks (condition notes or item notes, if available)

#### Scenario: Delivery address display
- **WHEN** DO PDF is generated
- **THEN** delivery address section shows buyer company address
- **AND** if buyer address is not available, shows placeholder or empty section

#### Scenario: Central purchasing section
- **WHEN** DO PDF is generated
- **THEN** signature section includes:
  - Prepared By (blank signature line)
  - Acknowledged By Head Admin (blank signature line)
  - Delivered By (blank signature line)
  - Accepted By (blank signature line)
  - Notes field (shipment notes if available, otherwise blank)

#### Scenario: PDF button in shipment view modal
- **WHEN** viewing shipments in "View Shipments" modal from Inbound Shipments relation manager
- **THEN** each shipment section displays a PDF download button
- **AND** button is visible for inbound shipments only
- **AND** clicking button opens PDF download route `/shipments/{shipment}/pdf`
- **AND** PDF is generated and downloaded with sanitized filename (replaces "/" with "-")

#### Scenario: PDF button in shipment view modal
- **WHEN** viewing shipments in "View Shipments" modal
- **THEN** each shipment section has a PDF download button
- **AND** button generates DO PDF for that specific shipment

#### Scenario: Handle missing optional data
- **WHEN** generating DO PDF for shipment with missing article data
- **THEN** Brand and Model columns show empty or "-" in item table
- **AND** PDF still generates successfully
- **WHEN** generating DO PDF for shipment with missing buyer address
- **THEN** delivery address section shows placeholder or is omitted
- **AND** PDF still generates successfully
