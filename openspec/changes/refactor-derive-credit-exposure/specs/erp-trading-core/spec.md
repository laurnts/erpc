## MODIFIED Requirements

### Requirement: Buyers Entity
The system SHALL manage buyer companies (using Company model with is_buyer=true) with credit limits, currency preferences, and associated people (contacts). Outstanding credit exposure and available credit SHALL be derived at read time from the buyer's confirmed orders that reserved credit, not read from a hand-maintained running counter.

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
- **WHEN** a buyer has a $50,000 credit limit and $30,000 of unreleased exposure from its own confirmed, credit-reserving orders
- **THEN** available credit is calculated as `max(0, credit_limit - credit_exposure)`, where `credit_exposure` is `SUM(total - credit_released)` over the buyer's orders with `status = 'confirmed' AND credit_reserved_at IS NOT NULL AND deleted_at IS NULL`, giving $20,000
- **AND** the calculation is `Company::derived_available_credit`, computed on the fly from `buyer_orders` — not read from the stored `available_credit` column
- **AND** a confirmed order that never reserved credit (e.g. placed while `credit_status` was disabled) contributes nothing to `credit_exposure`, even though it is confirmed

#### Scenario: Place buyer on credit hold
- **WHEN** an admin sets is_on_hold to true
- **THEN** the system warns when creating new credit orders for this buyer

#### Scenario: Request credit limit increase
- **WHEN** a user requests credit limit increase from $50,000 to $75,000
- **THEN** a BuyerCreditLimitRequest record is created with status PENDING
- **AND** the requested_credit_limit field is set to $75,000 on the buyer
- **AND** derived available credit remains unaffected until the request is approved (credit_limit has not changed)
- **AND** all finance approvers (finance role users with is_approver=true) are notified via email

#### Scenario: Approve credit limit increase (first approval)
- **WHEN** a finance approver (finance role user with is_approver=true) approves a credit limit increase request
- **THEN** an approval record is created linking the user to the request
- **AND** the request status remains PENDING (requires 2 approvals)
- **AND** derived available credit is unchanged (credit_limit has not changed yet)

#### Scenario: Approve credit limit increase (second approval)
- **WHEN** a second finance approver approves a credit limit increase request
- **THEN** an approval record is created linking the user to the request
- **AND** the request status changes to APPROVED
- **AND** the buyer's credit_limit is updated to the requested value
- **AND** derived available credit reflects the new limit immediately afterward, without any separate write to a stored available-credit column
- **AND** the requested_credit_limit field is cleared

#### Scenario: Reject credit limit increase
- **WHEN** a finance approver rejects a credit limit increase request with reason "Insufficient justification"
- **THEN** the request status changes to REJECTED
- **AND** the rejected_by_id, rejected_at, and rejected_reason are recorded
- **AND** the requested_credit_limit field is cleared on the buyer
- **AND** derived available credit is unaffected (credit_limit never changed)

#### Scenario: View credit limit requests
- **WHEN** a finance role user views the Credit Limit Requests page
- **THEN** all pending, approved, and rejected requests are displayed
- **AND** requests show buyer name, current limit, requested limit, status, and approval count

#### Scenario: View all buyers credit limits
- **WHEN** a finance role user views the Buyer Credit Limits Overview page
- **THEN** all buyers are listed with their credit limit and derived available credit, sorted and filtered in SQL via the buyer's credit-exposure query rather than computed per row in PHP
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
