# erp-trading-core Specification

## Purpose
TBD - created by archiving change add-erp-trading-system. Update Purpose after archive.
## Requirements
### Requirement: Tags/Categories System
The system SHALL provide a flat, shared tagging system (displayed as "Categories" in UI) that can be applied to both Articles and Suppliers.

#### Scenario: Create a new tag
- **WHEN** an admin creates a new tag with name "Chemicals" and color "#0066FF"
- **THEN** the tag is created with auto-generated slug "chemicals"
- **AND** the tag is scoped to the current team

#### Scenario: Apply tags to an article
- **WHEN** an admin assigns tags ["Chemicals", "Industrial"] to an article
- **THEN** the article is associated with both tags via polymorphic pivot

#### Scenario: Apply tags to a supplier
- **WHEN** an admin assigns tags ["Chemicals", "ISO-Certified"] to a supplier
- **THEN** the supplier is associated with both tags via polymorphic pivot

#### Scenario: Find suppliers by tag
- **WHEN** a user searches for suppliers with tag "Chemicals"
- **THEN** all suppliers with that tag are returned

#### Scenario: Prevent duplicate tags
- **WHEN** a user types "Chemical" when "Chemicals" already exists
- **THEN** the system suggests the existing tag via autocomplete

---

### Requirement: Currency Management
The system SHALL maintain a list of supported currencies with their properties (code, name, symbol, decimal places).

#### Scenario: List available currencies
- **WHEN** a user views the currency list
- **THEN** active currencies are displayed with code, name, symbol, and decimal places

#### Scenario: Create a new currency
- **WHEN** an admin creates currency with code "JPY", name "Japanese Yen", symbol "¥", decimals 0
- **THEN** the currency is available for selection in forms

#### Scenario: Deactivate a currency
- **WHEN** an admin deactivates a currency
- **THEN** the currency is no longer available for new transactions
- **AND** existing transactions retain their currency values

---

### Requirement: Exchange Rate Management
The system SHALL track historical exchange rates between currencies, allowing manual entry with effective dates.

#### Scenario: Record an exchange rate
- **WHEN** an admin records IDR/USD rate as 15,500 for date 2024-01-15
- **THEN** the rate is stored with source "manual" and the recording user

#### Scenario: Get exchange rate for a date
- **WHEN** the system needs IDR to USD rate for date 2024-01-20
- **THEN** it returns the most recent rate on or before that date (2024-01-15)

#### Scenario: Convert currency amount
- **WHEN** converting Rp 75,000,000 IDR to USD at rate 15,500
- **THEN** the result is $4,838.71 USD

#### Scenario: Warn on stale exchange rate
- **WHEN** the most recent exchange rate is older than 1 day
- **THEN** a warning is displayed suggesting rate update

---

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

### Requirement: Suppliers Entity
The system SHALL manage supplier companies (using Company model with is_supplier=true) with categories, currency preferences, default terms, and associated people (contacts).

#### Scenario: Create a supplier
- **WHEN** an admin creates a supplier with name "MotorCorp Indonesia"
- **THEN** a unique code is auto-generated (e.g., "CMP-0001")
- **AND** the supplier is scoped to the current team with is_supplier=true

#### Scenario: Assign contacts to supplier
- **WHEN** an admin assigns people to a supplier via the People/Contacts field
- **THEN** the associations are stored in the company_people pivot table
- **AND** the contacts count is displayed in the suppliers list

#### Scenario: Assign categories to supplier
- **WHEN** an admin assigns tags ["Industrial", "Motors"] to a supplier
- **THEN** the supplier is associated with those categories

#### Scenario: Set default payment terms
- **WHEN** an admin sets default_payment_terms to "Net 30"
- **THEN** new supplier orders default to those terms

#### Scenario: Set default lead time
- **WHEN** an admin sets default_lead_time_days to 14
- **THEN** new supplier quotes show this as expected lead time

---

### Requirement: Tax Codes Management
The system SHALL maintain a list of tax codes with rates for item-level tax handling.

#### Scenario: Create a tax code
- **WHEN** an admin creates tax code with code "PPN11", name "PPN 11%", rate 11.00
- **THEN** the tax code is available for selection on quote/order items
- **AND** the tax code is scoped to the current team

#### Scenario: Set default tax code
- **WHEN** an admin sets a tax code as the team default
- **THEN** new items default to this tax code
- **AND** only one tax code per team can be default

#### Scenario: Set inclusive default
- **WHEN** a tax code has is_inclusive_default = true
- **THEN** the "Price includes tax" checkbox defaults to checked when this code is selected

#### Scenario: Tax code ordering
- **WHEN** an admin reorders tax codes
- **THEN** sort_order determines dropdown display order
- **AND** default tax code appears first

#### Scenario: Deactivate a tax code
- **WHEN** an admin deactivates a tax code
- **THEN** the code is no longer available for new selections
- **AND** existing items retain their tax code snapshots

#### Scenario: Default tax codes seeded
- **WHEN** team ERP is initialized
- **THEN** default tax codes are created: "PPN 11%", "PPN 0%", "Tax Exempt", "No Tax"
- **AND** "PPN 11%" is set as team default

---

### Requirement: Articles Entity
The system SHALL manage articles (products/services) with flexible attributes stored as JSONB.

#### Scenario: Create an article
- **WHEN** an admin creates an article "Industrial Motor 5HP" with unit "pcs"
- **THEN** the article is created and scoped to the current team

#### Scenario: Add custom attributes
- **WHEN** an admin adds attributes {"voltage": "220V", "wattage": 1500, "certification": "CE"}
- **THEN** the attributes are stored as JSONB on the article

#### Scenario: Set default tax code on article
- **WHEN** an admin sets default_tax_code_id on an article
- **THEN** new quote/order items for this article default to that tax code

#### Scenario: Assign categories to article
- **WHEN** an admin assigns tags ["Industrial", "Motors"] to an article
- **THEN** the article is associated with those categories

#### Scenario: Link article to supplier
- **WHEN** a supplier quote includes an article
- **THEN** the supplier_articles pivot is updated with last_quoted_price and date

#### Scenario: Search articles by attributes
- **WHEN** a user searches for articles where voltage = "220V"
- **THEN** matching articles are returned using GIN index on attributes

---

### Requirement: Article-Supplier Relationship
The system SHALL manage a many-to-many relationship between Articles and Suppliers via the supplier_articles pivot table.

#### Scenario: Assign suppliers to article via Article form
- **WHEN** an admin creates or edits an article and selects suppliers from the Suppliers field in the Article form
- **THEN** the associations are stored in the supplier_articles pivot table
- **AND** the Suppliers field appears in the Article form after the Categories field
- **AND** the field supports multiple supplier selection
- **AND** the field is searchable and preloads options
- **AND** only active suppliers from the current team are shown (filtered by is_supplier=true)

#### Scenario: Create supplier inline from Article form
- **WHEN** an admin clicks the (+) button on the Suppliers field in the Article form
- **THEN** an inline supplier creation form appears
- **AND** the form uses `SupplierResource::getFormSchema(excludePeopleField: true)` for consistency
- **AND** the created supplier is automatically selected and linked to the article
- **AND** the supplier is created with is_supplier=true and scoped to the current team

#### Scenario: Assign suppliers to article via relation manager
- **WHEN** an admin assigns suppliers to an article via the Suppliers relation manager tab
- **THEN** the associations are stored in the supplier_articles pivot table
- **AND** pivot data includes supplier_sku, last_quoted_price, lead_time_days, is_preferred
- **NOTE:** This method remains available for detailed supplier-article relationship management

#### Scenario: Assign suppliers when creating article inline from Request Item
- **WHEN** an admin creates an article inline from the Request Item form and selects suppliers
- **THEN** the article is created with the selected suppliers assigned
- **AND** suppliers are synced via `$article->suppliers()->sync($data['suppliers'])`
- **AND** the Suppliers field works correctly in the modal context

#### Scenario: Assign articles to supplier
- **WHEN** an admin assigns articles to a supplier via the Articles field
- **THEN** the associations are stored in the supplier_articles pivot table
- **AND** pivot data includes supplier_sku, last_quoted_price, lead_time_days, is_preferred

#### Scenario: Set preferred supplier for article
- **WHEN** an admin sets is_preferred = true for a supplier-article link
- **THEN** that supplier becomes the preferred supplier for sourcing
- **AND** only one supplier per article can be preferred

#### Scenario: Track supplier pricing history
- **WHEN** a supplier quote includes an article
- **THEN** last_quoted_price, last_quoted_currency_id, and last_quoted_at are updated
- **AND** previous pricing is preserved in quote history

### Requirement: Projects Entity
The system SHALL allow grouping of multiple related Requests under a single Project for large deals.

#### Scenario: Create a project
- **WHEN** an admin creates a project "Q1 Factory Upgrade" for buyer "GlobalTrade"
- **THEN** a unique project_number is auto-generated (e.g., "PRJ-2024-0001")
- **AND** the project status defaults to "active"

#### Scenario: Add requests to project
- **WHEN** multiple requests are linked to a project
- **THEN** the project shows aggregated totals and status

#### Scenario: Project is optional
- **WHEN** a request is created without a project
- **THEN** the request functions independently

---

### Requirement: Requests Entity
The system SHALL manage Requests as the atomic unit representing a single buyer inquiry from initial request through final payment.

#### Scenario: Create a request
- **WHEN** an admin creates a request for buyer "GlobalTrade" with title "Factory Equipment Order"
- **THEN** a unique request_number is auto-generated (e.g., "REQ-2024-0001")
- **AND** the stage defaults to "new"
- **AND** base_currency defaults to system default

#### Scenario: Add request items
- **WHEN** an admin adds item "Tyre for Toyota Prius 2020" qty 4 pcs
- **THEN** the request_item is created with description (article_id nullable)

#### Scenario: Match request item to article
- **WHEN** an admin matches "Tyre for Toyota Prius" to "Michelin Pilot Sport 215/45R17"
- **THEN** article_id is set and is_matched becomes true

#### Scenario: Combined article-supplier selection
- **WHEN** an admin creates or edits a request item
- **THEN** the form shows a single "Match to Article" dropdown (full width)
- **AND** each option shows: "[CODE] Article Name → Supplier Name ★"
- **AND** articles without suppliers show: "[CODE] Article Name"
- **AND** preferred suppliers are marked with ★

#### Scenario: Select article and supplier together
- **WHEN** an admin selects an option from the dropdown
- **THEN** both article_id and supplier_id are set from the selection
- **AND** is_matched becomes true

#### Scenario: View item assignment status
- **WHEN** viewing request items in the Items tab
- **THEN** each item shows: match status (checkmark/X), article code, supplier name, quantity, unit
- **AND** a status summary shows "X/Y items matched" and "X/Y items assigned to suppliers"

#### Scenario: Clear selection
- **WHEN** an admin clears the "Match to Article" dropdown
- **THEN** both article_id and supplier_id are cleared
- **AND** is_matched becomes false

#### Scenario: Validate stage transition to sourcing
- **WHEN** stage transitions from "new" to "sourcing"
- **THEN** the transition is allowed (no prerequisites)

#### Scenario: Validate stage transition to supplier_quoting
- **WHEN** stage attempts transition to "supplier_quoting"
- **THEN** all request items must have article_id set
- **AND** validation fails if any items are unmatched

---

### Requirement: Request Stage Lifecycle
The system SHALL enforce a stage-based workflow for requests with defined transitions.

#### Scenario: Valid stage progression
- **WHEN** request progresses through stages: new → sourcing → supplier_quoting → buyer_quoting → negotiation → buyer_po_received → supplier_po_issued → fulfillment → invoicing → closed
- **THEN** each transition is valid and recorded

#### Scenario: Stage determines available actions
- **WHEN** request is in stage "new"
- **THEN** user can add/edit items but cannot create supplier quotes

#### Scenario: Stage closed marks completion
- **WHEN** request stage is set to "closed"
- **THEN** closed_at timestamp is recorded
- **AND** the request is considered complete

#### Scenario: Stage cancelled marks termination
- **WHEN** request stage is set to "cancelled"
- **THEN** the request is marked as terminated
- **AND** no further actions are allowed

---

### Requirement: Role-Based Access Control
The system SHALL provide role-based permissions using Spatie Laravel Permission package.

#### Scenario: Assign role to user
- **WHEN** an admin assigns role "sales" to a user via `$user->assignRole('sales')`
- **THEN** the user has permissions associated with that role

#### Scenario: Check permission
- **WHEN** a user attempts to record a payment
- **THEN** the system checks via `$user->can('erp.payments.record')`

#### Scenario: Default roles
- **WHEN** the system is initialized
- **THEN** default roles exist: superadmin, admin, sales, finance, viewer
- **AND** roles are seeded via `ErpPermissionsSeeder`

#### Scenario: Superadmin has all permissions
- **WHEN** a user has role "superadmin"
- **THEN** all permission checks return true via Gate::before hook

#### Scenario: Permission naming convention
- **WHEN** defining ERP permissions
- **THEN** names follow pattern `erp.{resource}.{action}` (e.g., `erp.requests.create`)

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

