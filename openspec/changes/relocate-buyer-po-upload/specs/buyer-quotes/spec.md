# Buyer Quotes Specification

## Purpose
Buyer Quotes enable the creation and management of consolidated quotes sent to buyers, including margin analysis, payment terms, and buyer purchase order (PO) file management. PO files can be uploaded and viewed when quotes are accepted by buyers.

## ADDED Requirements

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

