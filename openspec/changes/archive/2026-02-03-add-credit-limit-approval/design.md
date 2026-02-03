# Design: Credit Limit Increase Approval System

## Context

The system needs to support credit limit increase requests from buyers with a dual-approval workflow. Finance team members must approve requests before the credit limit is updated. This ensures proper financial controls and maintains an audit trail of all credit limit changes.

**Current State**: Credit limits can be changed directly by admins without approval workflow.

**Desired State**: Credit limit increases require request → notification → dual finance approval → automatic update.

## Goals / Non-Goals

### Goals
- Enable buyers to request credit limit increases through the UI
- Require 2 finance role users to approve before credit limit is updated
- Notify all finance role users when a request is created
- Maintain audit trail of all credit limit requests and approvals
- Display current active credit limit separately from requested limit
- Provide finance team with dedicated pages to manage requests and view all buyers' credit limits

### Non-Goals
- Credit limit decrease requests (only increases require approval)
- Single approver workflow (must be 2 approvers)
- Credit limit decrease approval workflow
- Automatic approval based on rules/thresholds
- Credit limit request history beyond current implementation
- Email notifications for approval/rejection (only for new requests)

## Decisions

### Decision: Separate Active Credit Limit from Requested Limit ✅
**Rationale**: The active credit limit should remain static until approved, while the requested limit can be changed. This prevents confusion and ensures the system uses the approved limit for credit checks.

**Implementation**: 
- Add `available_credit` field to `companies` table (defaults to current `credit_limit` on migration)
- Add `requested_credit_limit` field to `companies` table (nullable)
- `credit_limit` field remains for backward compatibility but will be updated only when request is approved

**Alternatives Considered**:
- Use only `credit_limit` field: Doesn't distinguish between active and requested, confusing
- Store requested limit only in request table: Makes it harder to see current requested amount on buyer form

### Decision: Dual Approval via Pivot Table ✅
**Rationale**: Need to track which finance users approved each request. A pivot table provides flexibility and clear audit trail.

**Implementation**: 
- Create `buyer_credit_limit_request_approvals` pivot table
- Fields: `buyer_credit_limit_request_id`, `user_id`, `approved_at`, `notes` (optional)
- Request is approved when 2 approvals exist

**Alternatives Considered**:
- JSON column with approver IDs: Less queryable, harder to enforce constraints
- Separate approval records table: More normalized but adds complexity
- Single approval field: Doesn't meet requirement for 2 approvers

### Decision: Request Status Enum ✅
**Rationale**: Clear status tracking (pending, approved, rejected) makes it easy to filter and display requests.

**Implementation**: 
- Create `CreditLimitRequestStatus` enum: `PENDING`, `APPROVED`, `REJECTED`
- Status automatically updates to `APPROVED` when 2 approvals received
- Status can be manually set to `REJECTED` by finance users

**Alternatives Considered**:
- Boolean flags: Less expressive, harder to extend
- String status: No type safety, harder to validate

### Decision: Email Notification on Request Creation Only ✅
**Rationale**: Finance users need to know when new requests are created. Approval/rejection notifications can be added later if needed.

**Implementation**: 
- Send email to all finance approvers when request is created
- Use existing `EmailTemplateService` and `TeamMemberService::getFinanceApprovers()`
- Email includes buyer name, current limit, requested limit, requester name

**Alternatives Considered**:
- Notify on approval/rejection: Adds complexity, can be added later
- In-app notifications only: Less immediate, finance users may miss requests
- Notify only first finance user: Doesn't meet requirement to notify all

### Decision: Finance Approver Designation ✅
**Rationale**: Not all finance role users should be able to approve credit limit requests. Adding an `is_approver` flag allows teams to designate specific finance users as approvers, providing better access control and separation of duties.

**Implementation**: 
- Add `is_approver` boolean field to `team_user` pivot table (default: false)
- Field is visible in member edit form only when `role === 'central_purchasing'` AND `central_purchasing_role === 'finance'`
- Only users with `is_approver = true` can approve credit limit requests
- Email notifications are sent only to finance approvers, not all finance users
- `TeamMemberService::getFinanceApprovers()` method filters finance users by `is_approver = true`

**Alternatives Considered**:
- All finance users can approve: Less secure, no separation of duties
- Separate approver role: Over-engineered, finance role with approver flag is sufficient
- Permission-based system: More complex, boolean flag is simpler and sufficient

### Decision: Finance Navigation Group for New Resources ✅
**Rationale**: Credit limit management is a finance function. Placing resources in Finance group keeps related functionality together.

**Implementation**: 
- `BuyerCreditLimitRequestResource` → Finance navigation group
- `BuyerCreditLimitOverviewResource` → Finance navigation group
- Both use `navigationGroup = 'Finance'`

**Alternatives Considered**:
- Master Data group: Less appropriate, credit limits are financial controls
- Separate Credit Management group: Overkill for 2 resources

### Decision: Update credit_limit Field on Approval ✅
**Rationale**: Maintain backward compatibility. Existing code that checks `credit_limit` will continue to work.

**Implementation**: 
- When request is approved (2 approvals), update both `credit_limit` and `available_credit` to requested value
- Clear `requested_credit_limit` after approval
- This ensures `availableCredit` calculation uses the new limit

**Alternatives Considered**:
- Only update `available_credit`: Breaks existing code that uses `credit_limit`
- Deprecate `credit_limit`: Too disruptive, requires extensive refactoring

## Architecture

### Data Flow

```
User Requests Credit Limit Increase
    ↓
Create BuyerCreditLimitRequest Record
    ↓
Set requested_credit_limit on Company
    ↓
Send Email to All Finance Users
    ↓
Finance User 1 Approves → Create Approval Record
    ↓
Finance User 2 Approves → Create Approval Record
    ↓
Check: 2 Approvals? → Update credit_limit & available_credit
    ↓
Clear requested_credit_limit
    ↓
Set Request Status to APPROVED
```

### Database Schema

**Companies Table (additions)**:
```sql
ALTER TABLE companies ADD COLUMN available_credit DECIMAL(15,2) DEFAULT 0;
ALTER TABLE companies ADD COLUMN requested_credit_limit DECIMAL(15,2) NULL;
```

**Buyer Credit Limit Requests Table**:
```sql
CREATE TABLE buyer_credit_limit_requests (
    id BIGSERIAL PRIMARY KEY,
    team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    buyer_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    current_limit DECIMAL(15,2) NOT NULL,
    requested_limit DECIMAL(15,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    requested_by_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rejected_by_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    rejected_at TIMESTAMP NULL,
    rejected_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

**Buyer Credit Limit Request Approvals Table**:
```sql
CREATE TABLE buyer_credit_limit_request_approvals (
    id BIGSERIAL PRIMARY KEY,
    buyer_credit_limit_request_id BIGINT NOT NULL REFERENCES buyer_credit_limit_requests(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    approved_at TIMESTAMP NOT NULL DEFAULT NOW(),
    notes TEXT NULL,
    UNIQUE(buyer_credit_limit_request_id, user_id)
);
```

**Team User Table (additions)**:
```sql
ALTER TABLE team_user ADD COLUMN is_approver BOOLEAN DEFAULT false NOT NULL;
```

### Model Relationships

**Company Model**:
- `hasMany(BuyerCreditLimitRequest::class, 'buyer_id')` - All requests for this buyer
- `belongsTo(User::class, 'requested_by_id')` - User who requested current pending increase

**BuyerCreditLimitRequest Model**:
- `belongsTo(Company::class, 'buyer_id')` - The buyer
- `belongsTo(User::class, 'requested_by_id')` - User who created request
- `belongsTo(User::class, 'rejected_by_id')` - User who rejected (if rejected)
- `belongsToMany(User::class, 'buyer_credit_limit_request_approvals')` - Approving users
- `hasMany(BuyerCreditLimitRequestApproval::class)` - Approval records

## Risks / Trade-offs

### Risk: Race Condition on Approval Count
**Mitigation**: Use database transaction with lock when checking approval count. Use `lockForUpdate()` when checking if 2 approvals exist.

### Risk: Finance Users Not Receiving Emails
**Mitigation**: Provide clear UI indication of pending requests. Finance users can check the Credit Limit Requests page even if email fails.

### Risk: Requested Limit Changed After Request Created
**Mitigation**: Store requested limit in request record. Buyer form shows current requested limit, but request record preserves original value.

### Risk: Credit Limit Updated Before Invoices Processed
**Mitigation**: Approval updates are immediate but can be rolled back if needed. Consider adding approval delay or scheduled update if needed.

### Trade-off: Simplicity vs Audit Trail
**Decision**: Store full request history in `buyer_credit_limit_requests` table. This provides complete audit trail but adds table size. Acceptable trade-off for financial controls.

### Trade-off: Email Notifications vs In-App Only
**Decision**: Email notifications ensure finance users are immediately aware. Can add in-app notifications later if needed.

## Migration Plan

### Phase 1: Database Schema
1. Add `available_credit` and `requested_credit_limit` to companies table
2. Migrate existing `credit_limit` values to `available_credit`
3. Create `buyer_credit_limit_requests` table
4. Create `buyer_credit_limit_request_approvals` table
5. Add `is_approver` field to `team_user` table

### Phase 2: Models and Enums
1. Create `CreditLimitRequestStatus` enum
2. Create `BuyerCreditLimitRequest` model
3. Create `BuyerCreditLimitRequestApproval` model
4. Update `Company` model with new fields and relationships

### Phase 3: UI Updates
1. Update BuyerResource Credit Settings section
2. Add request action to ViewBuyer page
3. Create BuyerCreditLimitRequestResource
4. Create BuyerCreditLimitOverviewResource

### Phase 4: Email and Approval Logic
1. Create CreditLimitIncreaseRequestMail mailable
2. Implement approval logic with transaction locking
3. Add email notification on request creation

### Rollback Plan
- Database migrations can be rolled back
- Remove new fields from Company model
- Delete new resources and models
- Existing `credit_limit` field remains unchanged, so no data loss

## Open Questions

- Should rejected requests be deletable or only cancellable? (Start with soft delete/rejection, can add deletion later)
- Should there be a maximum credit limit that requires additional approval? (Out of scope for now)
- Should finance users be able to modify the requested amount during approval? (Start with approve/reject only, can add modification later)
