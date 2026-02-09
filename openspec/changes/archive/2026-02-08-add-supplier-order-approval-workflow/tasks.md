## 1. Database Schema
- [x] 1.1 Create migration to add `approver_1_id`, `approver_2_id`, `approved_at` to `supplier_orders` table
- [x] 1.2 Add foreign key constraints and indexes
- [x] 1.3 Run migration

## 2. Enum Updates
- [x] 2.1 Add `APPROVED = 'approved'` case to `OrderStatus` enum
- [x] 2.2 Update `getLabel()`, `getColor()`, `getIcon()` methods
- [x] 2.3 Update `canSend()` to allow APPROVED status
- [x] 2.4 Add `canApprove()` method (returns true for CONFIRMED)
- [x] 2.5 Update `canCancel()` to exclude APPROVED
- [x] 2.6 Update `getNextStatus()` workflow method

## 3. Model Updates
- [x] 3.1 Add approval fields to `SupplierOrder` fillable array
- [x] 3.2 Add `approved_at` to casts array
- [x] 3.3 Add `approver1()` and `approver2()` relationships
- [x] 3.4 Add `isApproved()` accessor method
- [x] 3.5 Implement `approve(User $approver)` method with dual-approval logic
- [x] 3.6 Implement `canBeApprovedBy(User $user)` method for role checking
- [x] 3.7 Update `createFromQuote()` to recalculate line totals for taxable suppliers
- [x] 3.8 Add `recalculateTotals()` method to update order totals from items

## 4. SupplierOrderItem Model Updates
- [x] 4.1 Add model events (`saving`, `saved`, `deleted`) to recalculate line totals for taxable suppliers
- [x] 4.2 Ensure `tax_amount` and `unit_price_exc_tax` are calculated correctly
- [x] 4.3 Trigger order totals recalculation after item save/delete

## 5. Observer Updates
- [x] 5.1 Add `updated()` hook in `SupplierOrderObserver` to detect CONFIRMED status
- [x] 5.2 Send approval request emails to eligible approvers when status becomes CONFIRMED

## 6. Email Notification
- [x] 6.1 Create `SupplierOrderApprovalRequestMail` mailable class
- [x] 6.2 Include supplier order details and link to view order
- [x] 6.3 Configure to send to users with roles: DEPT_HEAD_SALES, DEPUTY_DIRECTOR, DIRECTOR
- [x] 6.4 Create email template `supplier-order-approval-request.blade.php` with custom HTML styling

## 7. Form Changes
- [x] 7.1 Remove `confirm_and_send` checkbox from `createFromBuyerOrder` form
- [x] 7.2 Remove email sending logic from `createFromBuyerOrder` action
- [x] 7.3 Keep only order creation logic
- [x] 7.4 Add Hidden fields for `tax_amount` and `unit_price_exc_tax` in items repeater
- [x] 7.5 Add `afterStateHydrated` hooks to recalculate line totals when items load
- [x] 7.6 Add reactive summary calculation using `Get $get` and `->live()`
- [x] 7.7 Add `handleRecordCreation` and `handleRecordUpdate` to recalculate totals after save

## 8. Create Approval Resource
- [x] 8.1 Create `SupplierOrderApprovalResource` in Approval navigation group
- [x] 8.2 Create `ListSupplierOrderApprovals` page with filtered query
- [x] 8.3 Create `ViewSupplierOrderApproval` page for viewing order details
- [x] 8.4 Create `SupplierOrderApprovalInfolist` schema for displaying order information
- [x] 8.5 Add approve action to list page table
- [x] 8.6 Add approve action to view page header
- [x] 8.7 Filter query to show orders where:
  - Status = APPROVED (all of them) OR Status = CONFIRMED (needs approval)
  - Orders remain visible after approval until sent
- [x] 8.8 Implement approve action handler with role checking and dual-approval logic
- [x] 8.9 Add approver name columns to list table using `getStateUsing`
- [x] 8.10 Implement `shouldRegisterNavigation()` to show menu for approval roles

## 9. Request Page Action Group Updates
- [x] 9.1 Remove `Approve` action from Request page supplier orders relation manager
- [x] 9.2 Remove `DownloadPdfAction` from default record actions
- [x] 9.3 Remove `send` action from default record actions
- [x] 9.4 Add conditional `DownloadPdfAction` (visible when status = APPROVED)
- [x] 9.5 Add conditional `send` action (visible when status = APPROVED)

## 10. PDF Template Updates
- [x] 10.1 Replace signature section with 4-column approval table
- [x] 10.2 Column 1: Display key account name(s) from `$order->request->buyer->keyAccounts`
- [x] 10.3 Column 2: Display `$order->approver1->name` if exists
- [x] 10.4 Column 3: Display `$order->approver2->name` if exists
- [x] 10.5 Column 4: Empty signature line for Supplier/Vendor
- [x] 10.6 Only show approval section when status is APPROVED
- [x] 10.7 Update item table to show tax column with currency formatting
- [x] 10.8 Ensure tax amount is calculated as `tax_amount × quantity`

## 11. Email Template Updates
- [x] 11.1 Update item table to show tax column correctly
- [x] 11.2 Fix tax column to display `tax_amount × quantity` instead of line_total
- [x] 11.3 Add Total column to item table
- [x] 11.4 Use currency formatting for all monetary values

## 12. Tax Calculation Fixes
- [x] 12.1 Update `SupplierOrder::createFromQuote()` to recalculate line totals for taxable suppliers
- [x] 12.2 Add model events to `SupplierOrderItem` to auto-recalculate line totals
- [x] 12.3 Update `calculateItemTotals()` to include tax in line_total for taxable suppliers
- [x] 12.4 Make summary section reactive to show tax_total as items are added/modified
- [x] 12.5 Ensure `tax_amount` field is saved when items are created/updated

## 13. Policy Updates
- [x] 13.1 Create `SupplierOrderApprovalPolicy` for approval resource
- [x] 13.2 Update `SupplierOrderPolicy::viewAny()` to allow approval roles
- [x] 13.3 Update `SupplierOrderPolicy::view()` to allow approval roles
- [x] 13.4 Register policy in `SupplierOrderApprovalResource`

## 14. Testing
- [x] 14.1 Test approval workflow with 2 different approvers
- [x] 14.2 Test that same user cannot approve twice
- [x] 14.3 Test email notifications are sent to correct roles
- [x] 14.4 Test PDF generation shows approval section correctly
- [x] 14.5 Test action group visibility based on status and user role
- [x] 14.6 Test that Send button only appears after approval
- [x] 14.7 Test tax calculation for taxable suppliers
- [x] 14.8 Test reactive summary updates
- [x] 14.9 Test approver names display in list and view pages
- [x] 14.10 Test orders remain visible after approval until sent

## 15. Documentation
- [x] 15.1 Update proposal.md with implementation details
- [x] 15.2 Update tasks.md with completion status
- [x] 15.3 Add comments to code explaining approval workflow
