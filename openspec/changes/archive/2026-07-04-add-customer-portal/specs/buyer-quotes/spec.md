# buyer-quotes Specification Delta

## ADDED Requirements

### Requirement: Customer Portal Quote Response
The system SHALL allow portal users to accept or reject buyer quotes sent for their requests.

#### Scenario: View sent quote on portal
- **WHEN** a buyer quote has status `sent` and belongs to a request visible to the portal user
- **THEN** the portal displays the quote with customer-facing price and validity date
- **AND** supplier cost and margin data are not shown

#### Scenario: Accept quote from portal
- **WHEN** a portal user accepts a sent buyer quote
- **THEN** the quote status changes to `accepted` via `markAsAccepted()`
- **AND** the action is recorded in request activity log
- **AND** internal staff are notified

#### Scenario: Reject quote from portal
- **WHEN** a portal user rejects a sent buyer quote
- **THEN** the quote status changes to `rejected` via `markAsRejected()`
- **AND** internal staff are notified

---

### Requirement: Customer Portal PO Upload
The system SHALL allow portal users to upload purchase order files for sent buyer quotes.

#### Scenario: Upload PO from portal
- **WHEN** a portal user uploads a PO file for a sent buyer quote
- **THEN** the file is stored in the quote's `buyer_po` media collection
- **AND** the quote status automatically changes to `accepted` (consistent with admin Upload PO behavior)

#### Scenario: View uploaded PO on portal
- **WHEN** a portal user has previously uploaded PO files
- **THEN** they can view and download their uploaded files
- **AND** they cannot delete files uploaded by internal staff without appropriate permission
