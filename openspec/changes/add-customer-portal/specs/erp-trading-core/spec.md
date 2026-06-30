# erp-trading-core Specification Delta

## ADDED Requirements

### Requirement: Portal-Originated Requests
The system SHALL support requests created by buyer contacts through the customer portal, using the same `Request` entity as internally created requests.

#### Scenario: Portal request creation source
- **WHEN** a request is created via the customer portal
- **THEN** `creation_source` is set to `portal` where applicable
- **AND** `submitted_by_user_id` references the portal user
- **AND** the request participates in the standard stage workflow after staff review

#### Scenario: Portal request enters standard workflow
- **WHEN** internal staff review a portal-submitted request and advance the stage
- **THEN** the request follows the same Goods or Service workflow as internally created requests
- **AND** no separate workflow table is required

#### Scenario: Backward compatibility
- **WHEN** an existing request has no `submission_method` value
- **THEN** it is treated as internally created
- **AND** no "From Portal" badge is displayed

---

### Requirement: Supplier Confidentiality in Portal Context
The system SHALL enforce that buyer portal users never see supplier-identifying information on any request data.

#### Scenario: Hide supplier quotes from portal
- **WHEN** a portal user views a request that has supplier quotes
- **THEN** supplier quote data is not exposed in any portal view or API response

#### Scenario: Hide article-supplier matching from portal
- **WHEN** a portal user views request items that have been matched to articles and suppliers internally
- **THEN** only the original customer-entered description, quantity, and UOM are shown
- **AND** article codes, supplier names, and match status are hidden

#### Scenario: Hide internal approval data from portal
- **WHEN** a portal user views a request
- **THEN** quotation evaluation, profit and loss, supplier orders, and internal notes are not accessible
