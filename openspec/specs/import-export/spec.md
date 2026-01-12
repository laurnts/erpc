# Import/Export Capability

## Purpose

CSV import and export functionality for CRM entities via Filament.

## Requirements

### Requirement: Data Import
The system SHALL support CSV import for CRM entities.

#### Scenario: Import Companies
- **WHEN** a user uploads a CSV file with company data
- **THEN** companies are created with mapped fields and custom fields

#### Scenario: Import Validation
- **WHEN** import rows have validation errors
- **THEN** they are recorded in failed_import_rows table for review

#### Scenario: Track Import Source
- **WHEN** entities are imported
- **THEN** the creation_source is set to IMPORT via CreationSource enum

### Requirement: Data Export
The system SHALL support CSV export for CRM entities.

#### Scenario: Export Companies
- **WHEN** a user requests a company export
- **THEN** a CSV file is generated with all fields including custom fields

#### Scenario: Export Filtering
- **WHEN** a user applies filters before export
- **THEN** only filtered records are included in the export

#### Scenario: Track Export
- **WHEN** an export is generated
- **THEN** the export record is created in the exports table

### Requirement: Bulk Operations
The system SHALL support bulk import/export operations.

#### Scenario: Queue Large Imports
- **WHEN** importing large datasets
- **THEN** the import is processed via queue for performance

#### Scenario: Progress Tracking
- **WHEN** a long-running import/export is in progress
- **THEN** the user can track progress via the Import/Export model

