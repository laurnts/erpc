## ADDED Requirements

### Requirement: Livewire Component Authorization
The system SHALL enforce authorization checks in all Livewire components that modify data.

#### Scenario: QuotationEvaluationForm authorization on mount
- **WHEN** user opens QuotationEvaluationForm for a Request
- **THEN** system verifies Request belongs to user's current team
- **AND** if Request is from another team, throws AuthorizationException
- **AND** user sees permission denied error

#### Scenario: QuotationEvaluationForm authorization on save
- **WHEN** user attempts to save a Quotation Evaluation
- **THEN** system checks `create quotation evaluations` permission
- **AND** if user lacks permission, shows error notification
- **AND** if user has permission, creates the record

#### Scenario: QuotationEvaluationForm authorization on create KeyAccount
- **WHEN** user attempts to create KeyAccount inline
- **THEN** system checks `create key accounts` permission
- **AND** if user lacks permission, shows error notification
- **AND** if user has permission, creates the KeyAccount

#### Scenario: SupplierQuoteComparison authorization on mount
- **WHEN** user opens SupplierQuoteComparison for a Request
- **THEN** system verifies Request belongs to user's current team
- **AND** if Request is from another team, throws AuthorizationException

#### Scenario: SupplierQuoteComparison authorization on apply selections
- **WHEN** user applies supplier selections
- **THEN** system checks `update supplier quotes` permission for each affected quote
- **AND** if user lacks permission for any quote, shows error notification
- **AND** if user has permission, updates quote items

#### Scenario: SupplierQuoteComparison quote ID validation
- **WHEN** user selects a supplier for an item
- **THEN** system validates the quote ID belongs to the current request
- **AND** if quote ID is invalid or from another request, selection is rejected

---

### Requirement: Authorization Trait for Livewire
The system SHALL provide a reusable trait for Livewire component authorization.

#### Scenario: Authorize action method
- **WHEN** component calls `authorizeAction(string $ability, mixed $model)`
- **THEN** trait delegates to Laravel Gate
- **AND** throws AuthorizationException if denied

#### Scenario: Team ownership validation
- **WHEN** component calls `ensureTeamOwnership(Model $model)`
- **THEN** trait compares model's team_id with current Filament tenant
- **AND** throws AuthorizationException if team mismatch

#### Scenario: Authorization failure logging
- **WHEN** authorization check fails
- **THEN** system logs warning with user ID, action, and resource
- **AND** log entry is available for security audit

---

### Requirement: Authorization Error Handling
The system SHALL show user-friendly errors for authorization failures.

#### Scenario: Permission denied notification
- **WHEN** user lacks required permission
- **THEN** Filament danger notification is shown
- **AND** notification title is "Permission Denied"
- **AND** notification body explains the required permission

#### Scenario: Team ownership error
- **WHEN** user attempts to access resource from another team
- **THEN** AuthorizationException is thrown
- **AND** user is redirected or shown error page
