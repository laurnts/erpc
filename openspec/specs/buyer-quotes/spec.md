# Buyer Quotes Specification

## Purpose
Buyer Quotes enable the creation and management of consolidated quotes sent to buyers, including margin analysis, payment terms, and buyer purchase order (PO) file management. PO files can be uploaded and viewed when quotes are accepted by buyers.
## Requirements
### Requirement: Buyer PO Upload Action Button
The system SHALL provide an "Upload PO" action button in the buyer quotes action table that appears next to the PDF button.

#### Scenario: Button appears for accepted quotes
- **WHEN** a buyer quote has status ACCEPTED
- **THEN** the Upload PO button SHALL be visible in the action table

#### Scenario: Button hidden for non-accepted quotes
- **WHEN** a buyer quote has status other than ACCEPTED
- **THEN** the Upload PO button SHALL NOT be visible

#### Scenario: Button label changes based on file existence
- **WHEN** no PO files have been uploaded for a buyer quote
- **THEN** the button label SHALL display "Upload PO"
- **WHEN** PO files have been uploaded for a buyer quote
- **THEN** the button label SHALL display "View PO"

#### Scenario: Upload PO button opens upload form
- **WHEN** user clicks the Upload PO button (no files exist)
- **THEN** a slide-over form SHALL open displaying a file upload component and file list
- **THEN** the form SHALL allow uploading multiple PO files

#### Scenario: View PO button opens view-only form
- **WHEN** user clicks the View PO button (files exist)
- **THEN** a slide-over form SHALL open displaying only the uploaded PO files list
- **THEN** the form SHALL NOT display a file upload component

### Requirement: Buyer PO Upload Slide-Over Form
The system SHALL provide a slide-over form for uploading buyer PO files.

#### Scenario: Upload new PO files
- **WHEN** user clicks Upload PO button and no files exist
- **THEN** the slide-over form SHALL display a file upload component allowing multiple file uploads
- **WHEN** user selects and uploads PO files
- **THEN** the files SHALL be saved to the buyer quote's media collection
- **THEN** the form SHALL refresh to show the uploaded files

### Requirement: Buyer PO View Slide-Over Form
The system SHALL provide a slide-over form for viewing buyer PO files (view-only, no upload capability).

#### Scenario: View uploaded PO files
- **WHEN** user clicks View PO button and files exist
- **THEN** the slide-over form SHALL display only a list of uploaded PO files with download and delete options
- **THEN** the form SHALL NOT include a file upload component
- **WHEN** user clicks download on a file
- **THEN** the file SHALL open/download correctly
- **WHEN** user clicks delete on a file
- **THEN** the file SHALL be removed after confirmation

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

