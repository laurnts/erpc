# Change: Add Profit and Loss (PNL) Document Generation

## Why
Users need to generate internal Profit and Loss (PNL) documents from the Buyer Quotes view. This document tracks profitability and includes approval workflow information for internal financial documentation.

## What Changes
- Add "Create PNL" button in Buyer Quotes section header (visible only when buyer quotes exist)
- Create modal form for PNL document creation with Central Purchasing approval fields
- New database table `profit_and_losses` to store PNL documents
- New Filament Resource `ProfitAndLossResource` in Master Data section
- Auto-generate PNL number with format: `{4-digit increment}/EL-PNL/{roman_month}/{year}`
- After PNL creation, redirect user to PNL view page
- PNL view page displays all captured data including approval workflow
- **PNL Status**: Computed status (Pending/Ordered) based on whether buyer orders exist for the request
- **Selected Items by Supplier**: Display buyer quote items grouped by supplier with cost, sell, tax, margin columns
- **Download PDF**: Generate landscape A4 PDF with items by supplier and approval section
- **Buyer Quote Link**: PNL stores reference to the latest valid buyer quote (excluding rejected/superseded)

## Impact
- Affected specs: `erp-quoting`
- Affected code:
  - New migration: `create_profit_and_losses_table`
  - New model: `App\Models\ProfitAndLoss`
  - New resource: `App\Filament\Resources\ProfitAndLossResource`
  - New policy: `App\Policies\ProfitAndLossPolicy`
  - Modified: `App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager`
  - Modified: `database/seeders/ErpPermissionSeeder`

## UI/UX Design

### Create PNL Button Location
- Located in Buyer Quotes section header, next to "New buyer quote" button
- Only visible when at least one buyer quote exists for the request
- Opens modal form on click

### PNL Creation Modal

```
┌─────────────────────────────────────────────────────┐
│ Create PNL                                       [X]│
├─────────────────────────────────────────────────────┤
│                                                     │
│ ┌─ PNL Information ─────────────────────────────┐   │
│ │ PNL Number: [Auto-generated after save]       │   │
│ │ Date: [Auto-filled: current date]             │   │
│ │ Request: REQ-2026-0042 (read-only)            │   │
│ │ Description: [____________________]           │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ Central Purchasing ──────────────────────────┐   │
│ │ Prepared By:      [Select Key Account ▼] [+]  │   │
│ │ Dept Head Sales:  [____________________]      │   │
│ │ Deputy Director:  [____________________]      │   │
│ │ Approved By:      [____________________]      │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│                              [Cancel] [Create PNL]  │
└─────────────────────────────────────────────────────┘
```

### PNL View Page (Master Data)

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back to List                    Profit & Loss    [Edit] [Del]│
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─ PNL Information ─────────────────────────────────────────┐   │
│ │ PNL Number: 0001/EL-PNL/I/2026                            │   │
│ │ Date: January 15, 2026                                    │   │
│ │ Request: REQ-2026-0042 (clickable link)                   │   │
│ │ Description: Motor Procurement Project                    │   │
│ └───────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─ Central Purchasing ──────────────────────────────────────┐   │
│ │ Prepared By    │ Dept Head Sales │ Deputy Dir │ Approved  │   │
│ │ John Smith     │ Jane Doe        │ Bob Wilson │ Alice B.  │   │
│ └───────────────────────────────────────────────────────────┘   │
│                                                                 │
│ Created by: Admin User | Created at: Jan 15, 2026 10:30 AM     │
└─────────────────────────────────────────────────────────────────┘
```

### PNL Number Format
- Pattern: `{4-digit increment}/EL-PNL/{roman_month}/{year}`
- Example: `0001/EL-PNL/I/2026` (January 2026, first PNL)
- Example: `0015/EL-PNL/XII/2026` (December 2026, 15th PNL)
- Increment resets yearly per team

## User Flow
1. User creates buyer quote(s) for a request
2. User clicks "Create PNL" button in Buyer Quotes section
3. Modal form opens with pre-filled data
4. User fills description and selects/enters approval personnel
5. User clicks "Create PNL"
6. PNL is created with auto-generated number
7. User is redirected to PNL View page in Master Data
