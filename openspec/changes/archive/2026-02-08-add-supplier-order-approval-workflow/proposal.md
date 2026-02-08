# Change: Add Supplier Order Approval Workflow

## Why

Supplier orders currently can be confirmed and sent directly to suppliers without proper approval controls. This creates financial risk as large purchase orders can be sent without oversight from management. This change introduces a mandatory dual-approval workflow requiring at least 2 approvals from senior roles (Dept Head of Sales, Deputy Director, or Director) before supplier orders can be sent to suppliers, ensuring proper financial controls and audit trails.

## What Changes

- **ADDED**: Dual-approval workflow for supplier orders requiring minimum 2 approvals from 3 eligible roles
- **ADDED**: New `APPROVED` status to OrderStatus enum
- **ADDED**: Database fields `approver_1_id`, `approver_2_id`, and `approved_at` to `supplier_orders` table
- **ADDED**: Email notification system to notify approvers when supplier order status becomes CONFIRMED
- **MODIFIED**: Supplier order creation form removes "Confirm and send to suppliers" checkbox option
- **ADDED**: New Filament resource "Supplier Orders" in Approval menu for dedicated approval workflow
- **MODIFIED**: Action group visibility logic in Request page:
  - Remove "Approve" button from Request page supplier orders relation manager
  - Remove "Send" and "View PDF" buttons from default actions
  - Show "Send" and "View PDF" buttons only when status = APPROVED
- **ADDED**: Approval actions in new Approval menu resource:
  - List page shows CONFIRMED orders needing approval OR APPROVED orders (until sent)
  - Approve action available on list and view pages
  - View page allows viewing order details before approval
  - Approver names displayed in list table columns
- **MODIFIED**: PDF template adds 4-column approval section at bottom showing:
  - Column 1: "Checked by" (key account name assigned to buyer)
  - Column 2: "Approved by" (first approver name)
  - Column 3: "Approved by" (second approver name)
  - Column 4: "Supplier/Vendor" (empty signature line)
- **MODIFIED**: PDF and Email templates display tax in item table:
  - Unit Price column shows price excluding tax
  - Tax column shows tax amount (tax_amount × quantity)
  - Total column shows line total including tax
- **MODIFIED**: Tax calculation for taxable suppliers:
  - Line totals automatically include tax when supplier is taxable
  - Tax total in summary section updates reactively as items are added/modified
  - Model events ensure totals are recalculated when items are saved
- **MODIFIED**: Status workflow: DRAFT → CONFIRMED → APPROVED → SENT (removes direct CONFIRMED → SENT path)

## Impact

- **Affected specs**: `erp-orders` (supplier order workflow and status management)
- **Affected code**:
  - `app/Enums/OrderStatus.php` - Add APPROVED status and update workflow methods
  - `app/Models/SupplierOrder.php` - Add approval fields, relationships, approval logic, and tax recalculation
  - `app/Models/SupplierOrderItem.php` - Add model events to recalculate line totals and order totals for taxable suppliers
  - `app/Observers/SupplierOrderObserver.php` - Add email notification on CONFIRMED status
  - `app/Filament/Resources/RequestResource/RelationManagers/SupplierOrdersRelationManager.php` - Remove approve action, update action group visibility, add reactive summary calculation, add tax calculation hooks
  - `app/Policies/SupplierOrderPolicy.php` - Update viewAny() and view() to allow approval roles
  - New: `app/Policies/SupplierOrderApprovalPolicy.php` - Policy for approval resource
  - New: `app/Filament/Resources/SupplierOrderApprovals/SupplierOrderApprovalResource.php` - New resource in Approval menu for approval workflow
  - New: `app/Filament/Resources/SupplierOrderApprovals/Pages/ListSupplierOrderApprovals.php` - List page with approve actions
  - New: `app/Filament/Resources/SupplierOrderApprovals/Pages/ViewSupplierOrderApproval.php` - View page with approve action and infolist
  - New: `app/Filament/Resources/SupplierOrderApprovals/Schemas/SupplierOrderApprovalInfolist.php` - Infolist schema for viewing order details
  - New: `resources/views/filament/infolists/components/supplier-order-items.blade.php` - Blade view for displaying items in infolist
  - `resources/views/pdf/supplier-order.blade.php` - Add approval section, update tax display in item table
  - `resources/views/emails/purchase-order-to-supplier.blade.php` - Update tax display in item table
  - New: `app/Mail/Erp/SupplierOrderApprovalRequestMail.php` - Email notification for approvers
  - New: `resources/views/emails/supplier-order-approval-request.blade.php` - Email template for approval requests
  - Database migration: Add approval fields to supplier_orders table
- **Breaking changes**: None - existing supplier orders remain functional, new approval workflow is additive
- **Dependencies**: Uses existing `TeamMemberService` for role-based queries, `EmailTemplateService` for notifications, `CentralPurchasingRole` enum for role checking

## Implementation Summary

### Database Changes
- Migration `2026_02_08_042237_add_approval_fields_to_supplier_orders_table.php` adds:
  - `approver_1_id` (foreign key to users table, nullable)
  - `approver_2_id` (foreign key to users table, nullable)
  - `approved_at` (timestamp, nullable)
  - Indexes on approver columns for performance

### Approval Workflow Implementation
1. **Order Creation**: Orders are created in DRAFT status (no auto-confirmation)
2. **Confirmation**: When order is confirmed, email notifications are sent to approvers
3. **Approval List**: New "Supplier Orders" menu under Approval shows:
   - CONFIRMED orders needing approval
   - APPROVED orders (remain visible until sent)
   - Approver names displayed in table columns
4. **Dual Approval**: Requires 2 different approvers from eligible roles
5. **Status Transition**: CONFIRMED → APPROVED (after 2nd approval) → SENT

### Tax Calculation Implementation
1. **Line Totals**: Automatically include tax for taxable suppliers
   - Formula: `line_total = (unit_price × quantity) + (unit_price × tax_rate/100 × quantity)`
2. **Model Events**: `SupplierOrderItem` automatically recalculates when saved
3. **Reactive Summary**: Summary section updates live as items are added/modified
4. **PDF/Email**: Tax column displays `tax_amount × quantity` with currency formatting

### Key Files Modified/Created
- **Models**: `SupplierOrder.php`, `SupplierOrderItem.php`
- **Policies**: `SupplierOrderPolicy.php`, `SupplierOrderApprovalPolicy.php` (new)
- **Resources**: `SupplierOrderApprovalResource.php` (new) with pages and schemas
- **Templates**: PDF and email templates updated for tax display
- **Forms**: Reactive summary calculation using Filament's `Get` and `live()` features

## Implementation Summary

### Database Changes
- Migration `2026_02_08_042237_add_approval_fields_to_supplier_orders_table.php` adds:
  - `approver_1_id` (foreign key to users table, nullable)
  - `approver_2_id` (foreign key to users table, nullable)
  - `approved_at` (timestamp, nullable)
  - Indexes on approver columns for performance

### Approval Workflow Implementation
1. **Order Creation**: Orders are created in DRAFT status (no auto-confirmation)
2. **Confirmation**: When order is confirmed, email notifications are sent to approvers
3. **Approval List**: New "Supplier Orders" menu under Approval shows:
   - CONFIRMED orders needing approval
   - APPROVED orders (remain visible until sent)
   - Approver names displayed in table columns
4. **Dual Approval**: Requires 2 different approvers from eligible roles
5. **Status Transition**: CONFIRMED → APPROVED (after 2nd approval) → SENT

### Tax Calculation Implementation
1. **Line Totals**: Automatically include tax for taxable suppliers
   - Formula: `line_total = (unit_price × quantity) + (unit_price × tax_rate/100 × quantity)`
2. **Model Events**: `SupplierOrderItem` automatically recalculates when saved
3. **Reactive Summary**: Summary section updates live as items are added/modified
4. **PDF/Email**: Tax column displays `tax_amount × quantity` with currency formatting

### Key Files Modified/Created
- **Models**: `SupplierOrder.php`, `SupplierOrderItem.php`
- **Policies**: `SupplierOrderPolicy.php`, `SupplierOrderApprovalPolicy.php` (new)
- **Resources**: `SupplierOrderApprovalResource.php` (new) with pages and schemas
- **Templates**: PDF and email templates updated for tax display
- **Forms**: Reactive summary calculation using Filament's `Get` and `live()` features
