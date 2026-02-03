# Change: Add Credit Limit Increase Approval System

## Why

Buyers need to request credit limit increases, and these requests require approval from finance team members before taking effect. Currently, credit limits can only be changed directly by admins without any approval workflow. This change introduces a dual-approval system where finance role users must approve credit limit increase requests, ensuring proper financial controls and audit trails.

## What Changes

- **ADDED**: Credit limit increase request system with dual finance approval workflow
- **ADDED**: New database fields to track active credit limit separately from requested credit limit
- **ADDED**: New `buyer_credit_limit_requests` table to track approval requests and approvals
- **ADDED**: Two new Filament resources in Finance navigation group:
  - Credit Limit Requests (list of pending/approved/rejected requests)
  - Buyer Credit Limits Overview (list of all buyers with their credit limits)
- **MODIFIED**: BuyerResource Credit Settings section to show:
  - Current active credit limit (read-only, static until approved)
  - Available credit (read-only, calculated from active limit)
  - Requested credit limit (editable field for new requests)
- **ADDED**: Email notification system to notify finance approvers when credit limit increase is requested
- **ADDED**: Approval tracking requiring 2 finance approvers to approve before credit limit is updated
- **ADDED**: `is_approver` field on `team_user` table to designate which finance users can approve requests

## Impact

- **Affected specs**: `erp-trading-core` (buyers and credit management)
- **Affected code**:
  - `app/Models/Company.php` - Add new fields and relationships
  - `app/Filament/Resources/BuyerResource.php` - Update Credit Settings section
  - `app/Filament/Resources/BuyerResource/Pages/ViewBuyer.php` - Add request action
  - New model: `app/Models/BuyerCreditLimitRequest.php`
  - New enum: `app/Enums/CreditLimitRequestStatus.php`
  - New resources: `app/Filament/Resources/BuyerCreditLimitRequestResource.php`, `app/Filament/Resources/BuyerCreditLimitOverviewResource.php`
  - New mailable: `app/Mail/Erp/CreditLimitIncreaseRequestMail.php`
  - Database migrations: Add fields to companies table, create buyer_credit_limit_requests table, add is_approver to team_user table
  - Updated: `app/Models/Membership.php` - Add is_approver cast
  - Updated: `app/Filament/Resources/MemberResource/Pages/ViewMember.php` - Add is_approver toggle field
  - Updated: `app/Services/TeamMemberService.php` - Add getFinanceApprovers() method
- **Breaking changes**: None - existing credit_limit field remains, new fields added alongside
- **Dependencies**: Uses existing `TeamMemberService` for finance role queries, `EmailTemplateService` for notifications
