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
The system SHALL manage articles (products/services) with flexible attributes stored as JSONB, multiple product images, an optional human-published public list price, and a flag controlling visibility in the public product grid.

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

#### Scenario: Upload multiple product images
- **WHEN** an admin uploads images to an article's Images section
- **THEN** they are stored in the `product_images` media collection (Spatie Media Library), reorderable, with thumbnail and medium conversions
- **AND** the first image is the primary image used on the public grid card

#### Scenario: Publish article to the public grid
- **WHEN** an admin enables `show_in_product_grid` on an article
- **THEN** the article becomes eligible for the public catalog (subject to `is_active`)
- **AND** the flag defaults to false for new and existing articles

#### Scenario: Publish a list price
- **WHEN** an admin saves a `list_price` (decimal 15,4, team default currency) on an article
- **THEN** `list_price_updated_at` is stamped and `price_review_needed` is cleared — saving is the publish act
- **AND** clearing the price makes the public catalog show "Price on request"

### Requirement: Article-Supplier Relationship
The system SHALL manage a many-to-many relationship between Articles and Suppliers via the supplier_articles pivot table, including supplier-owned standing price and available quantity fields that are distinct from the staff-owned quoted-price history, with at most one preferred supplier per article enforced at the database level.

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
- **AND** any previously preferred supplier for the article is demoted in the same transaction (shared `SetPreferredSupplier` action)
- **AND** a partial unique index (`article_id` where `is_preferred`) enforces at-most-one preferred supplier at the database level

#### Scenario: Track supplier pricing history
- **WHEN** a supplier quote includes an article
- **THEN** last_quoted_price, last_quoted_currency_id, and last_quoted_at are updated
- **AND** previous pricing is preserved in quote history

#### Scenario: Maintain supplier standing price
- **WHEN** a supplier (via the supplier portal) or staff (via the relation managers) set `supplier_price` with its currency on a supplier-article link
- **THEN** the value is stored on the pivot with `supplier_price_updated_at` stamped
- **AND** `supplier_price` (forward-looking standing offer, supplier-owned) remains distinct from `last_quoted_price` (backward-looking request history, staff-owned)

#### Scenario: Maintain available quantity
- **WHEN** a supplier or staff set `available_quantity` on a supplier-article link
- **THEN** the value and `quantity_updated_at` are stored on the pivot
- **AND** null means "unknown", which is distinct from 0

#### Scenario: Supplier-writable fields are confined
- **WHEN** a supplier updates their own supplier-article link
- **THEN** only `supplier_price`, `supplier_price_currency_id`, `available_quantity`, and `lead_time_days` are writable
- **AND** `is_preferred`, `is_active`, `supplier_sku`, `notes`, and `last_quoted_*` remain staff-only

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
The system SHALL manage Requests as the atomic unit representing a single buyer inquiry from initial request through final payment. Each request item carries its own Goods/Services type; the request derives its available workflows from the types of its items.

#### Scenario: Create a request
- **WHEN** an admin creates a request for buyer "GlobalTrade" with title "Factory Equipment Order"
- **THEN** a unique request_number is auto-generated (e.g., "REQ-2024-0001")
- **AND** the stage defaults to "draft"
- **AND** base_currency defaults to system default
- **AND** no request-level Goods/Services selection is offered

#### Scenario: Create a mixed request
- **WHEN** an admin adds a goods item and a services item to the same request
- **THEN** both items coexist on the request
- **AND** child items can be added to services main items
- **AND** shipments and acceptance reports are both available

#### Scenario: Add request items
- **WHEN** an admin adds item "Tyre for Toyota Prius 2020" qty 4 pcs
- **THEN** the request_item is created with description (article_id nullable)
- **AND** the item's type defaults to "goods" unless the admin selects "services"
- **AND** if the item's type is "services", a child items section appears after matching to article

#### Scenario: Match request item to article
- **WHEN** an admin matches "Tyre for Toyota Prius" to "Michelin Pilot Sport 215/45R17"
- **THEN** article_id is set and is_matched becomes true
- **AND** if the item's type is "services", this item becomes a main item and child items can be added

#### Scenario: Combined article-supplier selection
- **WHEN** an admin creates or edits a request item
- **THEN** the form shows a single "Match to Article" dropdown (full width)
- **AND** each option shows: "[CODE] Article Name → Supplier Name ★"
- **AND** articles without suppliers show: "[CODE] Article Name"
- **AND** preferred suppliers are marked with ★
- **AND** if the item's type is "services", the child items section appears below

#### Scenario: Select article and supplier together
- **WHEN** an admin selects an option from the dropdown
- **THEN** both article_id and supplier_id are set from the selection
- **AND** is_matched becomes true
- **AND** if the item's type is "services", the item becomes a main item

#### Scenario: View item assignment status
- **WHEN** viewing request items in the Items tab
- **THEN** each item shows: type badge (Goods/Services), match status (checkmark/X), article code, supplier name, quantity, unit
- **AND** child items of services main items are displayed with indentation or badge
- **AND** a status summary shows "X/Y items matched" and "X/Y items assigned to suppliers"

#### Scenario: Clear selection
- **WHEN** an admin clears the "Match to Article" dropdown
- **THEN** both article_id and supplier_id are cleared
- **AND** is_matched becomes false
- **AND** if the item's type is "services", child items are also cleared

#### Scenario: Validate stage transition to sourcing
- **WHEN** stage transitions from "draft" to "awaiting_supplier_response"
- **THEN** the transition is allowed (no prerequisites)

#### Scenario: Validate stage transition to supplier_quoting
- **WHEN** stage attempts transition to "preparing_buyer_quote"
- **THEN** all goods items and all services main items must have article_id set
- **AND** services child items are exempt from matching
- **AND** validation fails if any non-exempt item is unmatched

---

### Requirement: Request Stage Lifecycle
The system SHALL enforce a stage-based workflow for requests with defined transitions. A single stage progression applies to all requests; fulfillment-stage behavior derives from the types of the request's items.

#### Scenario: Valid stage progression
- **WHEN** a request progresses through stages: draft → awaiting_supplier_response → preparing_buyer_quote → awaiting_buyer_confirmation → preparing_supplier_order → fulfillment stages → invoiced → paid → completed
- **THEN** each transition is valid and recorded
- **AND** shipment tracking applies to the request's goods items
- **AND** acceptance reports apply to the request's services items

#### Scenario: Stage determines available actions
- **WHEN** request is in stage "draft"
- **THEN** user can add/edit items but cannot create supplier quotes
- **AND** child items can be added to services main items

#### Scenario: Stage closed marks completion
- **WHEN** request stage is set to "completed"
- **THEN** closed_at timestamp is recorded
- **AND** the request is considered complete

#### Scenario: Stage cancelled marks termination
- **WHEN** request stage is set to "cancelled"
- **THEN** the request is marked as terminated
- **AND** no further actions are allowed

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

### Requirement: Item Type Classification
The system SHALL classify each request item as Goods or Services, with the item's type determining its fulfillment channel, hierarchy support, and quoting behavior. Requests SHALL NOT carry a type of their own.

#### Scenario: Item type defaults to Goods
- **WHEN** a user adds an item to a request without choosing a type
- **THEN** the item's type is "goods"

#### Scenario: Create a mixed request
- **WHEN** a user creates a request with item "Industrial compressor" (goods) and item "Installation and commissioning" (services)
- **THEN** both items belong to the same request
- **AND** no request-level type selection is required or offered

#### Scenario: Service item supports child items
- **WHEN** an item's type is "services" and it is matched to an article
- **THEN** the user can add child items (description, quantity, unit of measure) under it
- **AND** child items inherit the parent item's type
- **AND** child items do not require article matching

#### Scenario: Goods item has no child items
- **WHEN** an item's type is "goods"
- **THEN** no child-items section is offered for that item

#### Scenario: Existing requests migrate to item-level types
- **WHEN** the migration runs on a request previously typed "services" with three items
- **THEN** all three items become type "services"
- **AND** the request-level type field is removed

---

### Requirement: Item-Level Fulfillment Channels
The system SHALL fulfill each item through the channel of its type — goods items via shipments, services main items via acceptance reports — and SHALL restrict each fulfillment document to items of its channel.

#### Scenario: Mixed request exposes both channels
- **WHEN** a request has one goods item and one services main item
- **THEN** shipments are available for the goods item
- **AND** acceptance reports are available for the services main item

#### Scenario: Service child items follow their parent
- **WHEN** a services main item is covered by an acceptance report
- **THEN** its child items are considered covered with it
- **AND** child items are never individually selectable on fulfillment documents

### Requirement: Acceptance Reports
The system SHALL provide acceptance reports as the fulfillment record for services items, available on any request that has at least one services item.

#### Scenario: Create acceptance report
- **WHEN** a user creates an acceptance report for a request with services items
- **THEN** a report_number is auto-generated (e.g., "AR-2026-0001")
- **AND** the report includes: reported date, reported by user, notes, and file uploads
- **AND** the report links to specific services request items (goods items are not offered)

#### Scenario: Upload acceptance report files
- **WHEN** a user uploads files to an acceptance report
- **THEN** the system accepts PDF, Word (.doc, .docx), and image files (.jpg, .jpeg, .png, .gif)
- **AND** files are stored using Spatie Media Library
- **AND** files can be previewed and downloaded from the acceptance report view

#### Scenario: Multiple acceptance reports per request
- **WHEN** a request has multiple acceptance reports
- **THEN** all reports are listed in the Acceptance Reports tab
- **AND** each report can be viewed, edited, or deleted independently

#### Scenario: Acceptance Reports tab visibility
- **WHEN** a request has at least one services item
- **THEN** the Acceptance Reports tab is visible (alongside the shipments tab when goods items also exist)
- **AND** the tab is hidden when the request has no services items

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

### Requirement: Safe Type Casting Utility
The system SHALL provide a utility class for validated type casting operations.

#### Scenario: Cast to float with validation
- **WHEN** calling `SafeCast::toFloat($value)`
- **AND** value is numeric
- **THEN** returns float representation
- **AND** if value is null or empty string, returns default (0.0)
- **AND** if value is non-numeric, returns default

#### Scenario: Cast to int with validation
- **WHEN** calling `SafeCast::toInt($value)`
- **AND** value is numeric
- **THEN** returns integer representation
- **AND** if value is null or empty string, returns default (0)
- **AND** if value is non-numeric, returns default

#### Scenario: Cast to string with validation
- **WHEN** calling `SafeCast::toString($value)`
- **AND** value is scalar
- **THEN** returns string representation
- **AND** if value is null, returns default ('')
- **AND** if value is array or object, returns default

---

### Requirement: PHPDoc Generic Annotations
The system SHALL use PHPDoc generics for Collection return types.

#### Scenario: Computed property with generic type
- **WHEN** Livewire component has computed property returning Collection
- **THEN** PHPDoc includes `@return Collection<int, ModelClass>`
- **AND** IDE provides autocomplete for collection items

#### Scenario: Query result with generic type
- **WHEN** variable holds Eloquent query result
- **THEN** PHPDoc annotation documents generic type
- **AND** loop variables have proper type inference

---

### Requirement: Array Shape Documentation
The system SHALL document complex array structures with PHPDoc array shapes.

#### Scenario: Snapshot data structure
- **WHEN** `QuotationEvaluation::buildSnapshotData()` returns array
- **THEN** PHPDoc documents full array shape
- **AND** includes nested array structures for items and suppliers
- **AND** PHPStan can validate array key access

#### Scenario: JSON data column access
- **WHEN** accessing `data` JSON column properties
- **THEN** typed getter methods provide safe access
- **AND** return types document expected structure
- **AND** null/missing keys return safe defaults

---

### Requirement: Typed Closure Parameters
The system SHALL type all closure parameters in functional operations.

#### Scenario: Collection map with typed closure
- **WHEN** using `$collection->map(fn ($item) => ...)`
- **THEN** closure parameter has explicit type: `fn (ModelClass $item): ReturnType =>`
- **AND** PHPStan validates closure body against type

#### Scenario: Collection filter with typed closure
- **WHEN** using `$collection->filter(fn ($item) => ...)`
- **THEN** closure parameter has explicit type
- **AND** return type is `bool`

---

### Requirement: Safe Numeric Operations
The system SHALL validate numeric values before calculations.

#### Scenario: Calculate line totals
- **WHEN** calculating item totals from form state
- **THEN** `SafeCast::toFloat()` validates input values
- **AND** invalid inputs default to zero
- **AND** no silent type coercion errors

#### Scenario: Format currency values
- **WHEN** formatting values with `number_format()`
- **THEN** input is validated as numeric first
- **AND** non-numeric values use safe default

### Requirement: Catalog List Pricing
The system SHALL provide an assisted price suggestion on the article form that applies the canonical `MarginConvention` and the existing `TeamErpSettings` default margin to supplier cost, and SHALL flag published prices for review when underlying supplier costs change; suggested or flagged prices SHALL never change the public price without an explicit admin save.

#### Scenario: Suggest a list price
- **WHEN** an admin triggers "Suggest price" on an article
- **THEN** the cost is resolved in order: preferred supplier's `supplier_price`, else preferred supplier's `last_quoted_price`, else the lowest converted `supplier_price` among active supplier links
- **AND** the cost is converted to the team default currency via the exchange-rate service
- **AND** the suggestion equals `MarginConvention::netUnitPrice(cost, TeamErpSettings default_margin_percent)`
- **AND** the suggestion only fills the input — the admin must save to publish

#### Scenario: Suggestion unavailable or partially convertible
- **WHEN** the resolved cost rung is a single preferred-supplier value and its exchange rate is missing
- **THEN** no suggestion is made and the notice names the missing currency
- **WHEN** the lowest-price rung is used and some candidates are unconvertible
- **THEN** those candidates are skipped and the notice lists the skipped suppliers/currencies; the action aborts only if no candidate converts

#### Scenario: Supplier price change flags review
- **WHEN** a supplier price write, preferred-supplier change, or the daily refresh command changes the best converted cost such that the published `list_price` yields a margin below the team default (or the cost becomes unconvertible)
- **THEN** `price_review_needed` is set on the article (persisted, recomputed at write time — never at public render time)
- **AND** the admin article list shows a review badge and a filter reading the persisted flag
- **AND** the public `list_price` is never changed automatically

### Requirement: Acceptance Report Team Scoping
Acceptance reports SHALL be team-scoped like all other document-owning ERP entities: `acceptance_reports` carries a `team_id` foreign key (cascade on delete), the model uses `HasTeam`, is registered in the enforced morph map, and new report numbers are sequenced per team per year with a matching `(team_id, report_number)` unique constraint.

#### Scenario: Team assigned on creation
- **WHEN** an acceptance report is created for a request
- **THEN** its `team_id` matches the parent request's team
- **AND** records are isolated per team via Filament panel tenancy and the `HasTeam` relationship

#### Scenario: Existing reports backfilled
- **WHEN** the migration runs on existing data
- **THEN** every existing acceptance report receives the `team_id` of its parent request
- **AND** the unique index moves from `(request_id, report_number)` to `(team_id, report_number)`

#### Scenario: Report numbering scoped per team and year, allocated from a locked counter
- **WHEN** a new acceptance report number is generated
- **THEN** the sequence increments per team per year (format `AR-{year}-{seq:04d}`), allocated from a locked counter row (`document_number_sequences`) rather than by reading the highest existing `report_number`
- **AND** previously issued report numbers are never rewritten or reissued, including when a report is deleted or when rows are inserted out of order

#### Scenario: Morph map registration repairs attachments
- **WHEN** an attachment is uploaded to an acceptance report
- **THEN** the media record persists using the `acceptance_report` morph alias on the `local` disk
- **AND** the upload no longer fails silently via a swallowed morph-map violation

### Requirement: Derived Fulfillment Completion
The system SHALL derive per-channel fulfillment completion on a request — the goods channel is complete when every goods main item is fully covered by **delivered** shipment quantities (pending, in-transit, and failed shipments do not count), the services channel is complete when every services main item is covered by an acceptance report, and a channel with no items is complete — and SHALL derive the request as fulfilled when all channels are complete. Stage progression remains manual, but the transition to the completed stage MUST require derived fulfillment.

#### Scenario: Mixed request fulfilled when both channels complete
- **WHEN** a request has goods items fully shipped and every services main item covered by an acceptance report
- **THEN** the request's derived fulfillment status is "fulfilled"

#### Scenario: Partially shipped goods block fulfillment
- **WHEN** a goods main item with quantity 10 has shipments covering only 6
- **THEN** the goods channel is incomplete
- **AND** the request's derived fulfillment status is not "fulfilled"

#### Scenario: Undelivered shipments do not count as coverage
- **WHEN** a goods main item is fully covered by shipment documents that are still pending or in transit
- **THEN** the goods channel is incomplete until those shipments are delivered

#### Scenario: Single-type request derives from its only channel
- **WHEN** a services-only request has every services main item covered by an acceptance report
- **THEN** the request's derived fulfillment status is "fulfilled" (the empty goods channel is vacuously complete)

#### Scenario: Completed stage gated on fulfillment
- **WHEN** a user attempts to move a request to the completed stage while its derived fulfillment status is not "fulfilled"
- **THEN** the transition is rejected with a message identifying the incomplete channel(s)

#### Scenario: Fulfillment status displayed
- **WHEN** a user views a request (view page or list)
- **THEN** the derived per-channel and overall fulfillment status is visible without opening fulfillment documents

### Requirement: Deal and Company Delete Protection via Financial Documents
The system SHALL prevent hard-deleting a `Request` or a `Company` once it has produced any
financial document anywhere in the deal chain (a buyer or supplier order, a buyer or supplier
invoice, a buyer or supplier payment, or a profit-and-loss record): the database SHALL reject the
delete via `RESTRICT` foreign keys on the documenting tables, and the `Request`/`Company` row SHALL
remain. Records in this state must be archived (soft-deleted) rather than hard-deleted; only a
`Request`/`Company` that has never produced a financial document can be force-deleted. Deleting a
`Team`, by contrast, remains a full cascade: every `team_id` foreign key on requests, companies,
and their financial documents is untouched `cascadeOnDelete()`, so a team delete removes the team
and everything under it in one statement, since a team delete represents intentional account
closure rather than routine record cleanup. This is safe specifically because PostgreSQL queues
referential-integrity checks as after-statement triggers: within the single `DELETE FROM teams`
statement, the cascades to `requests`, `companies`, and their financial documents (all still
`team_id` `CASCADE`) execute first, and only then do the RESTRICT triggers on e.g.
`buyer_invoices.request_id` fire — by which point the referencing `buyer_invoices` row has already
been removed by the team-id cascade, so there is no orphan left for the RESTRICT check to catch.
A direct `DELETE`/force-delete of the `requests` row on its own is a separate statement and has no
such cascade running ahead of it, so the RESTRICT trigger sees the still-live `buyer_invoices` row
and rejects it.

#### Scenario: A request with a financial document cannot be hard-deleted
- **WHEN** a request that has produced a buyer invoice (or any other financial document) is
  force-deleted
- **THEN** the database rejects the delete and the request row remains

#### Scenario: A company cannot be hard-deleted while its requests carry financial documents
- **WHEN** a buyer or supplier company whose request has produced a financial document is
  force-deleted
- **THEN** the delete is rejected once it reaches the protected document, and neither the company
  nor the request row is removed

#### Scenario: A request or company with no financial documents can still be hard-deleted
- **WHEN** a request (or company) that has never produced an order, invoice, payment, or
  profit-and-loss record is force-deleted
- **THEN** the delete succeeds and the row is removed

#### Scenario: Deleting a team still removes everything under it
- **WHEN** a `Team` is deleted
- **THEN** all of its requests, companies, and their financial documents are removed via
  cascading `team_id` foreign keys, in one statement, regardless of how many financial documents
  exist
- **AND** this succeeds even though the same rows are protected by a RESTRICT foreign key,
  because PostgreSQL fires the `team_id` CASCADE triggers before the RESTRICT triggers within the
  single delete statement, so the RESTRICT check never observes a referencing row

Verified directly against PostgreSQL 17 in a throwaway database mirroring the real constraint
shape (`teams` ← `requests` `ON DELETE CASCADE`; `buyer_invoices` → `teams` `CASCADE`, →
`requests` `RESTRICT`): `DELETE FROM requests WHERE id = 1` raises
`buyer_invoices_request_id_fkey ... still referenced from table "buyer_invoices"`, while
`DELETE FROM teams WHERE id = 1` succeeds and leaves `teams`, `requests`, and `buyer_invoices` all
empty. There is no automated regression test for the team-cascade half of this (see tasks.md).

### Requirement: Document Number Allocation
Request (`request_number`) and Project (`project_number`) numbers SHALL be allocated from a locked counter row (one row per team, document key, and calendar year in `document_number_sequences`) rather than by reading the highest existing number, so that concurrent creates cannot receive the same number and the sequence does not regress once a team's yearly count passes 9999 (a string-sorted "highest number" query would otherwise rank `'9999'` above `'10000'`). Numbers are strictly monotonic per (team, key, year): a rolled-back or deleted document permanently skips its number rather than having that number reissued to a later document.

#### Scenario: Concurrent request creates do not collide
- **WHEN** two requests are created for the same team at effectively the same time
- **THEN** each receives a distinct, correctly incrementing `request_number`
- **AND** neither create fails due to a duplicate-number save

#### Scenario: Sequence does not regress past 9999
- **WHEN** a team's project count for a year is already at 9999
- **THEN** the next allocated `project_number` sequence value is 10000, not a value already issued

#### Scenario: 30 rapid acceptance report allocations never collide
- **WHEN** 30 acceptance report numbers are allocated in immediate succession for the same team
- **THEN** all 30 numbers are distinct

