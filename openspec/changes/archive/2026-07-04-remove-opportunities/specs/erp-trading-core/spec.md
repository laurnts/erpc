# ERP Trading Core Delta

## MODIFIED Requirements

### Requirement: Company Role Classification
The system SHALL require every company to be classified as a buyer, a supplier, or both, and SHALL provide company management exclusively through the role-filtered Buyers and Suppliers views. There is no standalone Companies resource. This requirement is orthogonal to and composes with the Buyers Entity and Suppliers Entity requirements.

#### Scenario: No standalone Companies resource
- **WHEN** a user opens the navigation
- **THEN** no Companies entry exists in any group
- **AND** the Workspace group no longer exists; People, Notes, Tasks and the Tasks board page appear under Master Data alongside Buyers, Suppliers, Articles, and Tags

#### Scenario: Role required on every creation path
- **WHEN** a company is created via the Buyers view, the Suppliers view, or an inline company form
- **THEN** the resulting record has is_buyer=true, is_supplier=true, or both
- **AND** no creation path can produce a company with neither role

#### Scenario: Mark a buyer as also a supplier
- **WHEN** an admin checks "Also a supplier" on a buyer's form and saves
- **THEN** is_supplier is set to true on the same company record
- **AND** the company appears in both the Buyers and Suppliers lists

#### Scenario: Mark a supplier as also a buyer
- **WHEN** an admin checks "Also a buyer" on a supplier's form and saves
- **THEN** is_buyer is set to true on the same company record
- **AND** the company appears in both the Buyers and Suppliers lists

#### Scenario: Edit a dual-role company from either view
- **WHEN** an admin edits shared company fields (name, address, currency, payment terms) from the Buyers view or the Suppliers view
- **THEN** the same underlying company record is updated
- **AND** the change is visible in both views

#### Scenario: Login lands on Buyers
- **WHEN** a user logs in to the app panel
- **THEN** they are redirected to the Buyers list for their current team
- **AND** the panel home URL resolves to the Buyers list

#### Scenario: Company record links resolve to role views
- **WHEN** a company is linked from a person or a relation manager
- **THEN** the link opens the Buyer view when is_buyer=true, otherwise the Supplier view

#### Scenario: Soft-deleted companies remain accessible
- **WHEN** a company is soft-deleted
- **THEN** it can be found and restored via the trashed filter on the Buyers and/or Suppliers list according to its roles

#### Scenario: Onboarding seeds no role-less companies
- **WHEN** a new team is seeded with demo data
- **THEN** every seeded company has at least one of is_buyer/is_supplier set
