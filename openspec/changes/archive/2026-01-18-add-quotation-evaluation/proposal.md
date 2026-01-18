# Change: Add Quotation Evaluation (QE) Document Generation

## Why
Users need to generate internal Quotation Evaluation (QE) documents from the Compare Supplier Quotes view. This document consolidates supplier comparison data with approval workflow information for internal procurement decision-making and documentation.

## What Changes
- Add "Create QE" button next to "Select Best Prices" in Compare Supplier Quotes
- Create slide-over modal form for QE document creation
- New database table `quotation_evaluations` to store QE documents
- New database table `key_accounts` (master data) to store key account personnel
- New Filament Resource `QuotationEvaluationResource` in Master Data section
- Auto-generate QE number with format: `{increment}-DS/QE/{roman_month}/{year}`
- Display Central Purchasing section with approval workflow fields
- After QE creation, redirect user to QE view page
- QE view page displays all captured data including item comparison table
- Item Comparison, Supplier Information, and Central Purchasing sections displayed full width
- Supplier Information section placed after Item Comparison section
- Display unit price and item total (before tax) for each item in comparison table
- Download PDF button to export QE in landscape A4 format

## Impact
- Affected specs: `erp-quoting`
- Affected code:
  - New migration: `create_key_accounts_table`
  - New migration: `create_quotation_evaluations_table`
  - New model: `App\Models\KeyAccount`
  - New model: `App\Models\QuotationEvaluation`
  - New resource: `App\Filament\Resources\QuotationEvaluationResource`
  - New resource: `App\Filament\Resources\KeyAccountResource`
  - Modified: `App\Livewire\SupplierQuoteComparison`
  - New: `App\Livewire\QuotationEvaluationForm`

## UI/UX Design

### QE Creation Modal (Slide-over from right)

```
┌─────────────────────────────────────────────────────┐
│ Create Quotation Evaluation                      [X]│
├─────────────────────────────────────────────────────┤
│                                                     │
│ ┌─ QE Information ────────────────────────────────┐ │
│ │ QE Number: [Auto-generated after save]          │ │
│ │ Date: [Auto-filled: current date]               │ │
│ │ Request: REQ-2026-0042 (read-only)              │ │
│ │ Description: [Project name - editable]          │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ┌─ Central Purchasing ────────────────────────────┐ │
│ │                                                 │ │
│ │ Prepared By:                                    │ │
│ │ [Select Key Account ▼] [+]                      │ │
│ │                                                 │ │
│ │ Acknowledged By:                                │ │
│ │   Dept Head of Sales: [Select Key Account ▼][+]│ │
│ │   Deputy Director:    [Select Key Account ▼][+]│ │
│ │                                                 │ │
│ │ Approved By:                                    │ │
│ │ [Select Key Account ▼] [+]                      │ │
│ │                                                 │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│                              [Cancel] [Save QE]     │
└─────────────────────────────────────────────────────┘
```

### QE View Page (Master Data - after redirect)

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back to List              Quotation Evaluation    [Edit] [Del]│
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─ QE Information ────────────────────────────────────────────┐ │
│ │ QE Number: 001-DS/QE/I/2026                                 │ │
│ │ Date: January 15, 2026                                      │ │
│ │ Request: REQ-2026-0042 (clickable link)                     │ │
│ │ Description: Motor Procurement Project                      │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─ Item Comparison ───────────────────────────────────────────┐ │
│ │ ┌────────────┬─────┬────────────┬────────────┬────────────┐ │ │
│ │ │ Item       │ Qty │ MotorCorp  │ PumpWorld  │ SupplierC  │ │ │
│ │ ├────────────┼─────┼────────────┼────────────┼────────────┤ │ │
│ │ │ Motor 5HP  │ 2   │ ★ $4,800   │ $5,200     │ $5,000     │ │ │
│ │ │ Water Pump │ 1   │ $1,200     │ ★ $1,100   │ $1,150     │ │ │
│ │ │ Valve Set  │ 5   │ $500       │ $480       │ ★ $450     │ │ │
│ │ ├────────────┼─────┼────────────┼────────────┼────────────┤ │ │
│ │ │ Subtotal         │ $6,500     │ $6,780     │ $6,600     │ │ │
│ │ │ Tax              │ $715       │ $746       │ $726       │ │ │
│ │ │ Grand Total      │ $7,215     │ $7,526     │ $7,326     │ │ │
│ │ └────────────┴─────┴────────────┴────────────┴────────────┘ │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─ Supplier Information ──────────────────────────────────────┐ │
│ │ ┌───────────────┬────────────┬────────────┬────────────────┐│ │
│ │ │               │ MotorCorp  │ PumpWorld  │ SupplierC      ││ │
│ │ ├───────────────┼────────────┼────────────┼────────────────┤│ │
│ │ │ Delivery Type │ Franco     │ Loco       │ Franco         ││ │
│ │ │ Taxable       │ Yes        │ No         │ Yes            ││ │
│ │ │ Delivery Term │ 14 days    │ 7 days     │ 10 days        ││ │
│ │ │ Payment Terms │ Net 30     │ Net 14     │ Net 21         ││ │
│ │ └───────────────┴────────────┴────────────┴────────────────┘│ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─ Central Purchasing ────────────────────────────────────────┐ │
│ │                                                             │ │
│ │ Prepared By:        John Smith (john@company.com)           │ │
│ │                                                             │ │
│ │ Acknowledged By:                                            │ │
│ │   Dept Head of Sales: Jane Doe (jane@company.com)           │ │
│ │   Deputy Director:    Bob Wilson (bob@company.com)          │ │
│ │                                                             │ │
│ │ Approved By:         Alice Brown (alice@company.com)        │ │
│ │                                                             │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ Created by: Admin User | Created at: Jan 15, 2026 10:30 AM     │
└─────────────────────────────────────────────────────────────────┘
```

### Key Account Form (Modal when clicking [+])

```
┌─────────────────────────────────────────────────────┐
│ Create Key Account                               [X]│
├─────────────────────────────────────────────────────┤
│                                                     │
│ Name:  [____________________________________]       │
│                                                     │
│ Email: [____________________________________]       │
│                                                     │
│ Phone: [____________________________________]       │
│                                                     │
│                              [Cancel] [Create]      │
└─────────────────────────────────────────────────────┘
```

### QE Number Format
- Pattern: `{increment}-DS/QE/{roman_month}/{year}`
- Example: `001-DS/QE/I/2026` (January 2026, first QE)
- Example: `015-DS/QE/XII/2026` (December 2026, 15th QE)
- Increment resets yearly per team

## Master Data

### Key Accounts
Key Accounts is a master data table storing personnel involved in the approval workflow:
- Used across multiple QE documents
- Can be reused for Prepared By, Acknowledged By, and Approved By fields
- Contains: Name, Email, Phone Number

### Quotation Evaluations
Listed in Master Data navigation with:
- Table columns: QE Number, Request Number, Description, Date, Created By
- View page with full details (as shown above)
- Edit capability for Central Purchasing fields
- Delete capability

## User Flow
1. User opens Compare Supplier Quotes
2. User clicks "Create QE" button
3. Slide-over form opens with pre-filled data
4. User selects/creates approval personnel
5. User clicks "Save QE"
6. QE is created with auto-generated number
7. User is redirected to QE View page in Master Data
