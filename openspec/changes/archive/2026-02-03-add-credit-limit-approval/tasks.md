# Tasks: Add Credit Limit Increase Approval System

## Phase 1: Database Schema

### 1.1 Add Credit Limit Fields to Companies Table
- [x] 1.1.1 Create migration to add `available_credit` column (decimal 15,2, default 0)
- [x] 1.1.2 Create migration to add `requested_credit_limit` column (decimal 15,2, nullable)
- [x] 1.1.3 Create migration to migrate existing `credit_limit` values to `available_credit`
- [x] 1.1.4 Run migrations and verify data migration

### 1.2 Create Credit Limit Request Tables
- [x] 1.2.1 Create migration for `buyer_credit_limit_requests` table with:
  - `id`, `team_id`, `buyer_id`, `current_limit`, `requested_limit`
  - `status` (string, default 'pending')
  - `requested_by_id`, `rejected_by_id`, `rejected_at`, `rejected_reason`
  - `created_at`, `updated_at`
  - Foreign keys and indexes
- [x] 1.2.2 Create migration for `buyer_credit_limit_request_approvals` pivot table with:
  - `id`, `buyer_credit_limit_request_id`, `user_id`
  - `approved_at`, `notes` (nullable)
  - Unique constraint on (request_id, user_id)
  - Foreign keys and indexes
- [x] 1.2.3 Run migrations and verify table creation

## Phase 2: Models and Enums

### 2.1 Create Credit Limit Request Status Enum
- [x] 2.1.1 Create `app/Enums/CreditLimitRequestStatus.php` with cases:
  - `PENDING`, `APPROVED`, `REJECTED`
- [x] 2.1.2 Implement `HasLabel`, `HasColor`, `HasIcon` interfaces
- [x] 2.1.3 Add appropriate colors and icons for each status

### 2.2 Create Buyer Credit Limit Request Model
- [x] 2.2.1 Create `app/Models/BuyerCreditLimitRequest.php` model
- [x] 2.2.2 Add fillable fields and casts (status enum, decimal casts)
- [x] 2.2.3 Add relationships:
  - `belongsTo(Company::class, 'buyer_id')`
  - `belongsTo(User::class, 'requested_by_id')`
  - `belongsTo(User::class, 'rejected_by_id')`
  - `belongsToMany(User::class, 'buyer_credit_limit_request_approvals')` → `approvers()`
- [x] 2.2.4 Add `HasTeam` trait
- [x] 2.2.5 Add helper methods:
  - `approve(User $user, ?string $notes = null)` - Add approval
  - `reject(User $user, string $reason)` - Reject request
  - `isApproved()` - Check if 2 approvals exist
  - `approvalCount()` - Get current approval count
  - `canBeApprovedBy(User $user)` - Check if user can approve (not already approved, is finance role)

### 2.3 Create Buyer Credit Limit Request Approval Model
- [x] 2.3.1 Create `app/Models/BuyerCreditLimitRequestApproval.php` model
- [x] 2.3.2 Add fillable fields and timestamps
- [x] 2.3.3 Add relationships:
  - `belongsTo(BuyerCreditLimitRequest::class)`
  - `belongsTo(User::class)`

### 2.4 Update Company Model
- [x] 2.4.1 Add `available_credit` and `requested_credit_limit` to fillable array
- [x] 2.4.2 Add decimal casts for new fields
- [x] 2.4.3 Add relationship: `hasMany(BuyerCreditLimitRequest::class, 'buyer_id')`
- [x] 2.4.4 Add helper method: `pendingCreditLimitRequest()` - Get current pending request
- [x] 2.4.5 Update `availableCredit` attribute to use `available_credit` instead of `credit_limit` (Note: Attribute was removed per user request, field is used directly)

## Phase 3: BuyerResource UI Updates

### 3.1 Update Credit Settings Section
- [x] 3.1.1 Update `BuyerResource::getFormSchema()` Credit Settings section:
  - Add `available_credit` field (read-only, displays current available credit)
  - Add `availableCredit` display (read-only, shows available credit)
  - Modify `credit_limit` field label to "Active Credit Limit" (read-only)
  - Add `requested_credit_limit` field (editable, for new requests)
- [x] 3.1.2 Add conditional logic to show appropriate fields based on request status
- [x] 3.1.3 Update field prefixes/suffixes to use team currency

### 3.2 Add Request Action to ViewBuyer Page
- [x] 3.2.1 Update `ViewBuyer::getHeaderActions()` to add "Request Credit Limit Increase" action
- [x] 3.2.2 Create action that:
  - Opens modal/form to enter requested credit limit
  - Validates requested limit > current active limit
  - Creates `BuyerCreditLimitRequest` record
  - Sets `requested_credit_limit` on Company
  - Sends email notification to finance approvers
  - Shows success notification
- [x] 3.2.3 Hide action if pending request exists or if requested limit equals active limit

## Phase 4: Finance Resources

### 4.1 Create Buyer Credit Limit Request Resource
- [x] 4.1.1 Create `app/Filament/Resources/BuyerCreditLimitRequestResource.php`
- [x] 4.1.2 Set navigation group to 'Finance'
- [x] 4.1.3 Configure table with columns:
  - Buyer (name, code)
  - Current Limit, Requested Limit, Increase Amount
  - Status (badge with color)
  - Requested By, Requested At
  - Approval Count (X/2)
  - Approvers (list of names)
- [x] 4.1.4 Add filters: Status, Buyer, Requested By
- [x] 4.1.5 Add actions:
  - Approve (only if user hasn't approved and < 2 approvals)
  - Reject (only if pending)
  - View Details (slide-over or page)
  - View Approval Notes (modal showing all approval notes with approver name, date, and notes)
    - [x] Add "View Approval Notes" action button to table actions
    - [x] Create Blade view template for approval notes modal display
    - [x] Update Eloquent query to eager load `approvals.user` relationship
    - [x] Configure modal to show approver name, approval date/time, and notes
- [x] 4.1.6 Create `ListCreditLimitRequests` page

### 4.2 Create Buyer Credit Limit Overview Resource
- [x] 4.2.1 Create `app/Filament/Resources/BuyerCreditLimitOverviewResource.php`
- [x] 4.2.2 Set navigation group to 'Finance'
- [x] 4.2.3 Configure table with columns:
  - Buyer (name, code)
  - Active Credit Limit
  - Credit Used
  - Available Credit
  - Requested Credit Limit (if pending)
  - Status (On Hold, Active)
  - Last Updated
- [x] 4.2.4 Add filters: On Hold, Has Pending Request
- [x] 4.2.5 Add sorting by credit limit, available credit
- [x] 4.2.6 Create `ListBuyerCreditLimits` page

## Phase 5: Email Notification System

### 5.1 Create Credit Limit Increase Request Mailable
- [x] 5.1.1 Create `app/Mail/Erp/CreditLimitIncreaseRequestMail.php`
- [x] 5.1.2 Implement `envelope()` with subject: "Credit Limit Increase Request: [Buyer Name]"
- [x] 5.1.3 Implement `content()` with:
  - Buyer name and code
  - Current active credit limit
  - Requested credit limit
  - Increase amount
  - Requester name
  - Link to request detail page
- [x] 5.1.4 Create email view template: `resources/views/emails/credit-limit-increase-request.blade.php`

### 5.2 Implement Email Notification Logic
- [x] 5.2.1 In request creation action, query finance approvers via `TeamMemberService::getFinanceApprovers($team)`
- [x] 5.2.2 Extract email addresses from finance approvers
- [x] 5.2.3 Use `EmailTemplateService::sendWithTeamSettings()` to send email to all finance approvers
- [x] 5.2.4 Handle email sending errors gracefully (log error, show warning notification)
- [x] 5.2.5 Add email sending to request creation action

## Phase 6: Finance Approver Designation

### 6.1 Add Is Approver Field to Team User Table
- [x] 6.1.1 Create migration to add `is_approver` boolean column to `team_user` table (default: false)
- [x] 6.1.2 Add `is_approver` cast to `Membership` model
- [x] 6.1.3 Add `is_approver` toggle field to `ViewMember` edit form (visible only when finance role)
- [x] 6.1.4 Update `ViewMember::fillForm()` to include `is_approver` value
- [x] 6.1.5 Update `ViewMember` form action to save/clear `is_approver` based on role changes

### 6.2 Update Service and Approval Logic
- [x] 6.2.1 Add `getFinanceApprovers()` method to `TeamMemberService` that filters by `is_approver = true`
- [x] 6.2.2 Update `BuyerCreditLimitRequest::canBeApprovedBy()` to use `getFinanceApprovers()` instead of all finance users
- [x] 6.2.3 Update `ViewBuyer` email notification to use `getFinanceApprovers()` and update message text

## Phase 7: Approval Logic

### 7.1 Implement Approval Workflow
- [x] 7.1.1 In `BuyerCreditLimitRequest::approve()` method:
  - Check user hasn't already approved (unique constraint)
  - Check user is a finance approver (has finance role AND is_approver=true)
  - Create approval record
  - Check if 2 approvals exist
  - If yes, update Company `credit_limit` and increase `available_credit` by increase amount
  - Clear `requested_credit_limit` on Company
  - Update request status to APPROVED
  - Use database transaction with lock to prevent race conditions
- [x] 7.1.2 In `BuyerCreditLimitRequest::reject()` method:
  - Set rejected_by_id, rejected_at, rejected_reason
  - Update status to REJECTED
  - Clear `requested_credit_limit` on Company
  - Use database transaction

### 7.2 Add Approval Actions to Resource
- [x] 7.2.1 Add "Approve" action to BuyerCreditLimitRequestResource table:
  - Visible only if request is pending and user hasn't approved and user is a finance approver
  - Calls `approve()` method with current user
  - Shows success notification
  - Refreshes table
- [x] 7.2.2 Add "Reject" action to BuyerCreditLimitRequestResource table:
  - Opens modal to enter rejection reason
  - Calls `reject()` method with current user and reason
  - Shows success notification
  - Refreshes table

## Phase 8: Testing

### 8.1 Unit Tests
- [x] 8.1.1 Test BuyerCreditLimitRequest model relationships
- [x] 8.1.2 Test approval workflow (single approval, dual approval, status updates)
- [x] 8.1.3 Test rejection workflow
- [x] 8.1.4 Test Company model new fields and relationships
- [x] 8.1.5 Test availableCredit calculation with available_credit
- [x] 8.1.6 Test is_approver field visibility and behavior in Membership model
- [x] 8.1.7 Test getFinanceApprovers() method filters correctly

### 8.2 Feature Tests
- [x] 8.2.1 Test credit limit increase request creation
- [x] 8.2.2 Test email notification sent to finance approvers only
- [x] 8.2.3 Test approval workflow (first approval, second approval triggers update)
- [x] 8.2.4 Test rejection workflow
- [x] 8.2.5 Test BuyerCreditLimitRequestResource list and actions
- [x] 8.2.6 Test BuyerCreditLimitOverviewResource list
- [x] 8.2.7 Test BuyerResource Credit Settings section updates
- [x] 8.2.8 Test non-approver finance users cannot approve requests
- [x] 8.2.9 Test is_approver field clears when role changes

### 8.3 Integration Tests
- [x] 8.3.1 Test full workflow: Request → Email → Approve (2x) → Credit Limit Updated
- [x] 8.3.2 Test race condition prevention (concurrent approvals)
- [x] 8.3.3 Test email notification with multiple finance approvers
- [x] 8.3.4 Test rejection clears requested limit
- [x] 8.3.5 Test approver designation workflow

## Phase 9: Documentation

### 9.1 Code Documentation
- [x] 9.1.1 Add PHPDoc comments to new models and methods
- [x] 9.1.2 Document approval workflow in BuyerCreditLimitRequest model
- [x] 9.1.3 Document email notification system
- [x] 9.1.4 Document is_approver field and getFinanceApprovers() method