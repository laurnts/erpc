# erp-shipments Specification Delta

## ADDED Requirements

### Requirement: Shipment Eligibility by Item Type
The system SHALL offer shipment tracking for a request when the request has at least one goods item, and SHALL restrict shipment contents to goods items.

#### Scenario: Shipments tab on a mixed request
- **WHEN** a request has one goods item and one services item
- **THEN** the Inbound Shipments tab is visible
- **AND** the Acceptance Reports tab is visible alongside it

#### Scenario: Shipments tab hidden without goods items
- **WHEN** all of a request's items are services items
- **THEN** no shipments tab is shown for the request (admin panel and customer portal)

#### Scenario: Shipment item picker excludes service items
- **WHEN** an admin adds items to a shipment for a mixed request
- **THEN** only goods items are offered for selection
- **AND** services items never appear on shipment documents
