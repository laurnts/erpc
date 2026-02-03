## MODIFIED Requirements

### Requirement: Buyers Entity
The system SHALL manage buyer companies (using Company model with is_buyer=true) with credit limits, currency preferences, and associated people (contacts).

#### Scenario: Create a buyer
- **WHEN** an admin creates a buyer with name "GlobalTrade Industries"
- **THEN** a unique code is auto-generated (e.g., "CMP-0001")
- **AND** the buyer is scoped to the current team with is_buyer=true

#### Scenario: Assign contacts to buyer
- **WHEN** an admin assigns people to a buyer via the People/Contacts field
- **THEN** the associations are stored in the company_people pivot table
- **AND** the contacts count is displayed in the buyers list

#### Scenario: Set credit limit
- **WHEN** an admin sets credit limit to $50,000 for a buyer
- **THEN** the credit limit is stored on the buyer record
- **AND** the available_credit is also set to $50,000

#### Scenario: Calculate available credit
- **WHEN** a buyer has $50,000 active credit limit and $30,000 unpaid invoices
- **THEN** available credit is calculated as $20,000
- **AND** the calculation uses available_credit, not credit_limit

#### Scenario: Place buyer on credit hold
- **WHEN** an admin sets is_on_hold to true
- **THEN** the system warns when creating new credit orders for this buyer

#### Scenario: Request credit limit increase
- **WHEN** a user requests credit limit increase from $50,000 to $75,000
- **THEN** a BuyerCreditLimitRequest record is created with status PENDING
- **AND** the requested_credit_limit field is set to $75,000 on the buyer
- **AND** the available_credit remains $50,000 until approved
- **AND** all finance approvers (finance role users with is_approver=true) are notified via email

#### Scenario: Approve credit limit increase (first approval)
- **WHEN** a finance approver (finance role user with is_approver=true) approves a credit limit increase request
- **THEN** an approval record is created linking the user to the request
- **AND** the request status remains PENDING (requires 2 approvals)
- **AND** the available_credit remains unchanged

#### Scenario: Approve credit limit increase (second approval)
- **WHEN** a second finance approver approves a credit limit increase request
- **THEN** an approval record is created linking the user to the request
- **AND** the request status changes to APPROVED
- **AND** the buyer's credit_limit and available_credit are updated to the requested value
- **AND** the requested_credit_limit field is cleared

#### Scenario: Reject credit limit increase
- **WHEN** a finance approver rejects a credit limit increase request with reason "Insufficient justification"
- **THEN** the request status changes to REJECTED
- **AND** the rejected_by_id, rejected_at, and rejected_reason are recorded
- **AND** the requested_credit_limit field is cleared on the buyer
- **AND** the available_credit remains unchanged

#### Scenario: View credit limit requests
- **WHEN** a finance role user views the Credit Limit Requests page
- **THEN** all pending, approved, and rejected requests are displayed
- **AND** requests show buyer name, current limit, requested limit, status, and approval count

#### Scenario: View all buyers credit limits
- **WHEN** a finance role user views the Buyer Credit Limits Overview page
- **THEN** all buyers are listed with their active credit limit, credit used, available credit
- **AND** buyers with pending requests show the requested credit limit

#### Scenario: Prevent duplicate approvals
- **WHEN** a finance role user attempts to approve the same request twice
- **THEN** the system prevents the duplicate approval
- **AND** an error message is displayed

#### Scenario: Prevent non-finance approval
- **WHEN** a user without finance role attempts to approve a credit limit request
- **THEN** the approval action is not available
- **AND** the user cannot approve the request

#### Scenario: Prevent non-approver finance user from approving
- **WHEN** a finance role user without is_approver=true attempts to approve a credit limit request
- **THEN** the approval action is not available
- **AND** the user cannot approve the request

#### Scenario: Custom fields on buyer
- **WHEN** team has custom fields configured for buyers
- **THEN** the buyer form includes those custom fields

## ADDED Requirements

### Requirement: Credit Limit Increase Request System
The system SHALL provide a workflow for requesting and approving credit limit increases with dual finance approval.

#### Scenario: Create credit limit increase request
- **WHEN** a user requests a credit limit increase for a buyer
- **THEN** a BuyerCreditLimitRequest record is created with:
  - Buyer reference, current limit, requested limit
  - Status set to PENDING
  - Requested by user and timestamp recorded
- **AND** the buyer's requested_credit_limit field is set to the requested value
- **AND** email notifications are sent to all users with finance role in the team

#### Scenario: Track credit limit request approvals
- **WHEN** a finance approver approves a credit limit request
- **THEN** a BuyerCreditLimitRequestApproval record is created
- **AND** the approval links the user, request, timestamp, and optional notes
- **AND** duplicate approvals by the same user are prevented

#### Scenario: Automatic credit limit update on approval
- **WHEN** a credit limit request receives 2 approvals from different finance users
- **THEN** the buyer's credit_limit and available_credit are automatically updated to the requested value
- **AND** the requested_credit_limit field is cleared
- **AND** the request status changes to APPROVED
- **AND** the update occurs within a database transaction to prevent race conditions

#### Scenario: Credit limit request rejection
- **WHEN** a finance role user rejects a credit limit request
- **THEN** the request status changes to REJECTED
- **AND** the rejected_by_id, rejected_at, and rejected_reason are recorded
- **AND** the buyer's requested_credit_limit field is cleared
- **AND** the available_credit remains unchanged

### Requirement: Credit Limit Request Management Pages
The system SHALL provide dedicated Filament resources for finance users to manage credit limit requests and view all buyers' credit limits.

#### Scenario: List credit limit requests
- **WHEN** a finance role user navigates to Credit Limit Requests page
- **THEN** all credit limit requests are displayed in a table
- **AND** columns show buyer name, current limit, requested limit, status, requester, approval count
- **AND** requests can be filtered by status, buyer, requester
- **AND** pending requests show approval actions

#### Scenario: Approve credit limit request from list
- **WHEN** a finance approver clicks Approve on a pending request they haven't approved
- **THEN** an approval record is created
- **AND** if this is the second approval, the credit limit is automatically updated
- **AND** a success notification is displayed
- **AND** the table refreshes to show updated status

#### Scenario: Reject credit limit request from list
- **WHEN** a finance approver clicks Reject on a pending request
- **THEN** a modal opens to enter rejection reason
- **AND** when submitted, the request is rejected
- **AND** the buyer's requested_credit_limit is cleared
- **AND** a success notification is displayed

#### Scenario: View all buyers credit limits
- **WHEN** a finance role user navigates to Buyer Credit Limits Overview page
- **THEN** all buyers are listed with their credit limit information
- **AND** columns show buyer name, active credit limit, credit used, available credit, requested limit (if pending)
- **AND** buyers can be filtered by on-hold status and pending request status
- **AND** buyers can be sorted by credit limit or available credit

### Requirement: Credit Limit Request Email Notifications
The system SHALL notify all finance role users via email when a credit limit increase is requested.

#### Scenario: Send email notification on request creation
- **WHEN** a credit limit increase request is created
- **THEN** email notifications are sent to all finance approvers (finance role users with is_approver=true) in the team
- **AND** the email includes:
  - Buyer name and code
  - Current active credit limit
  - Requested credit limit
  - Increase amount
  - Requester name
  - Link to view the request
- **AND** emails use the team's email settings (SMTP, sender, etc.)
- **AND** email sending failures are logged but do not prevent request creation

## ADDED Requirements

### Requirement: Finance Approver Designation
The system SHALL allow designation of specific finance role users as approvers who can approve credit limit increase requests.

#### Scenario: Designate finance user as approver
- **WHEN** an admin edits a team member with finance role
- **THEN** an "Is Approver" toggle field is visible
- **AND** the toggle can be enabled to mark the user as an approver
- **AND** only users with is_approver=true can approve credit limit requests

#### Scenario: Approver field visibility
- **WHEN** editing a team member
- **THEN** the "Is Approver" field is visible only when role is "central_purchasing" AND central_purchasing_role is "finance"
- **AND** the field is hidden for all other roles

#### Scenario: Clear approver flag on role change
- **WHEN** a finance approver's role is changed away from finance or central_purchasing
- **THEN** the is_approver flag is automatically cleared (set to false)
- **AND** the user can no longer approve credit limit requests
