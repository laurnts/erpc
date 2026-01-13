# UI/UX Gap Analysis: Wireframes vs Filament Implementation

## Overview

This document analyzes the gaps between the wireframes defined in `README/uiux.md` and the current Filament implementation. The wireframes serve as the layout blueprint; Filament components should be used to implement them.

**Last Updated:** 2026-01-13
**Status:** Alignment completed for core sections.

**Status Legend:**
- ✅ Aligned - Implementation matches wireframe
- ⚠️ Partial - Some elements match, others need work
- ❌ Gap - Significant divergence from wireframe

## Summary of Completed Alignments

| Section | Status | Notes |
|---------|--------|-------|
| Navigation Structure | ✅ Aligned | Reorganized to Trading, Master Data, Finance, Settings groups |
| Dashboard Widgets | ✅ Aligned | KPI widgets verified |
| Categories (Tags) | ✅ Aligned | Renamed to Categories, added count columns, description |
| Article Form | ✅ Aligned | Simplified modal: Name → SKU → Unit → Categories → Description → Custom Attributes |
| Supplier Form | ✅ Aligned | Simplified modal: Name → Code → Categories → Contact/Email → Phone/Currency → Payment/Lead Time |
| Request Form | ✅ Aligned | Simplified modal: Request# → Buyer → Stage/Priority → Dates → Description → Notes → Project |
| Tag (Category) Form | ✅ Aligned | Simplified modal: Name → Color → Description |
| Buyer Form | ✅ Aligned | Simplified modal: Name → Code → Contact/Email → Phone/Country → Credit/Payment Terms |
| Request Detail | ✅ Aligned | Added command center layout, financial summary, stage progress |
| Exchange Rates | ✅ Aligned | Added inverse rate display, description |
| Activity Log | ✅ Aligned | Created RequestActivitiesRelationManager with icons/colors |
| System Audit Log | ⚠️ Deferred | Admin module feature - not in core scope |

---

## 1. Navigation Structure

**Wireframe Reference:** Section 1 of uiux.md

| Element | Wireframe | Current | Status | Notes |
|---------|-----------|---------|--------|-------|
| Navigation Groups | Trading, Master Data, Finance, Settings | Trading, ERP, ERP Settings | ⚠️ Partial | Groups need renaming |
| Requests as primary | ★ MAIN workspace | Listed under Trading | ✅ Aligned | Correct placement |
| Master Data grouping | Buyers, Suppliers, Articles, Categories | Split across groups | ⚠️ Partial | Should consolidate |

**Required Changes:**
1. Rename navigation groups to match wireframe hierarchy:
   - `Trading` → Requests, Projects
   - `Master Data` → Buyers, Suppliers, Articles, Categories (Tags)
   - `Finance` → Buyer Invoices, Supplier Invoices, Payments, Exchange Rates
   - `ERP Settings` → Currencies, Tax Codes (move to Settings group)

---

## 2. Dashboard

**Wireframe Reference:** Section 2 of uiux.md

### 2.1 KPI Stats Row

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Active Requests | ✓ Present | `ActiveRequestsWidget` | ✅ Aligned |
| Quotes Expiring | "⚠️ next 7 days" | `QuotesExpiringWidget` | ✅ Aligned |
| Awaiting Payment | "$45,230, 3 overdue" | `AwaitingPaymentWidget` | ✅ Aligned |
| Monthly Revenue | "$128,450 Gross Margin" | `MonthlyRevenueWidget` | ✅ Aligned |

### 2.2 Pipeline & Attention Widgets

| Element | Wireframe | Current | Status | Gap |
|---------|-----------|---------|--------|-----|
| Pipeline by Stage | Horizontal bar chart | `PipelineByStageWidget` | ⚠️ Partial | Review chart style |
| Requires Attention | List with [Extend] [View] actions | `RequiresAttentionWidget` as table | ⚠️ Partial | Missing inline action buttons |

**Required Changes:**
1. `RequiresAttentionWidget`: Add inline action buttons per wireframe:
   ```
   • REQ-0089 Quote expires in 2d
     [Extend] [View]
   ```
   Current shows table with single "View" action.

2. Pipeline widget: Verify bar chart matches wireframe style with stage labels.

---

## 3. Categories (Tags) Management

**Wireframe Reference:** Section 3 of uiux.md

### Wireframe Layout:
```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│ 📁 Categories                                                    [+ New Category]   │
├─────────────────────────────────────────────────────────────────────────────────────┤
│ Categories are shared between Articles and Suppliers for classification.            │
│                                                                                      │
│ 🔍 [Search categories...                                  ]                         │
│                                                                                      │
│ ┌─────────────────────────────────────────────────────────────────────────────────┐ │
│ │  Category         Articles    Suppliers    Color                                │ │
```

### Current Implementation (`TagResource.php`):

| Element | Wireframe | Current | Status | Gap |
|---------|-----------|---------|--------|-----|
| Page title | "📁 Categories" | "Tags" | ❌ Gap | Rename to "Categories" |
| Description text | "Categories are shared..." | None | ❌ Gap | Add page description |
| Table columns | Category, Articles, Suppliers, Color | name, color, description, sort_order | ❌ Gap | Missing count columns |
| Count columns | Show Articles/Suppliers count | Not present | ❌ Gap | Add relationship counts |
| Warning message | "⚠️ Deleting a category will..." | None | ❌ Gap | Add footer warning |

**Required Changes:**

1. **Rename resource:**
   ```php
   protected static ?string $navigationLabel = 'Categories';
   protected static ?string $modelLabel = 'Category';
   protected static ?string $pluralModelLabel = 'Categories';
   ```

2. **Add relationship count columns:**
   ```php
   TextColumn::make('articles_count')
       ->counts('articles')
       ->label('Articles'),
   TextColumn::make('suppliers_count')
       ->counts('suppliers')
       ->label('Suppliers'),
   ```

3. **Add page description** in ListTags page or as a Section header.

---

## 4. Article Form

**Wireframe Reference:** Section 4 of uiux.md

### Wireframe Layout:
```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│ CREATE ARTICLE                                                               [✕]    │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  Article Name *
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │ Acetone Technical Grade 99.5%                                               │
│
│  SKU (Optional)
│  ...
│  Categories                                                       [+ Create New]
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │ [Chemicals ✕] [Industrial ✕] [Solvent ✕]                                    │
│  ...
│  ── CUSTOM ATTRIBUTES ──
│  ...
│  ────────────────────────────────────────────────────────────────────────────────
│  [Cancel]                                                     [Create Article]
```

### Current Implementation (`ArticleResource.php`):

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Form style | Modal with [✕] close | Modal via CreateAction | ✅ Aligned |
| Field: Article Name | First field, prominent | `name` - first field | ✅ Aligned |
| Field: SKU | Second field | `sku` - second field | ✅ Aligned |
| Field: Unit of Measure | Third field | `unit` - third field | ✅ Aligned |
| Field: Categories (Tags) | Tag-style multi-select with inline create | `tags` Select with createOptionForm | ✅ Aligned |
| Custom Attributes | Key-value collapsible | `KeyValue` in collapsible Section | ✅ Aligned |
| Footer buttons | [Cancel] [Create Article] | Filament default | ✅ Aligned |

**Implementation Complete** - Form simplified to match wireframe layout:
- Single-column layout with fields in wireframe order
- Categories with inline create (Name + Color picker)
- Custom Attributes in collapsible section

---

## 5. Supplier Form

**Wireframe Reference:** Section 5 of uiux.md

### Wireframe Layout:
```
│  Company Name *
│  Code (auto-generated)
│  Categories (What they supply)                                [+ Create New]
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │ [Chemicals ✕] [Industrial ✕] [Hazmat ✕] [ISO-Certified ✕]                   │
│
│  Contact Name                          Email
│  Phone                                 Default Currency
│  Default Payment Terms                 Default Lead Time
```

### Current Implementation (`SupplierResource.php`):

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Company Name | First field | `name` - first field | ✅ Aligned |
| Code | "auto-generated" shown | Disabled field with placeholder | ✅ Aligned |
| Categories field | Tag-style with hint | `tags` Select with helperText | ✅ Aligned |
| Contact Name / Email | Side-by-side row | Section with 2 columns | ✅ Aligned |
| Phone / Currency | Side-by-side row | Section with 2 columns | ✅ Aligned |
| Payment Terms / Lead Time | Side-by-side row | Section with 2 columns | ✅ Aligned |

**Implementation Complete** - Form simplified to match wireframe layout:
- Single-column layout with row groupings using Section::make()->columns(2)
- Categories with inline create and helper text
- Three 2-column rows: Contact/Email, Phone/Currency, PaymentTerms/LeadTime

---

## 6. Request Detail - Command Center

**Wireframe Reference:** Section 6/7 of uiux.md

This is the most complex view. The wireframe shows a "command center" layout.

### 6.1 Header Section

**Wireframe:**
```
│  ┌───────────────────────────────────────────────────────────────────────────────┐
│  │                           REQUEST HEADER                                       │
│  │  REQ-2024-0089                                              [Edit] [···]      │
│  │  Factory Equipment Order - Industrial motors and controls                     │
│  │  Buyer: GlobalTrade Industries                          Created: Jan 5, 2024  │
│  │         📧 john@globaltrade.com  📞 +1-555-0123         Updated: Jan 8, 2024  │
│  │         Credit: $50,000 available                                             │
│  │  Base Currency: USD  │  Suppliers may quote in: IDR, USD, etc.                │
│  └───────────────────────────────────────────────────────────────────────────────┘
```

**Current (`ViewRequest.php`):**

| Element | Wireframe | Current | Status | Gap |
|---------|-----------|---------|--------|-----|
| Request number prominent | Bold, first element | `TextEntry` with weight('bold') | ✅ Aligned | |
| Buyer contact inline | Email + Phone + Credit on same card | Buyer name only, links elsewhere | ❌ Gap | Missing inline contact |
| Currency note | "Base Currency: USD..." | Not present | ❌ Gap | Add currency context |
| Edit button in header | [Edit] [···] | ActionGroup with Edit | ✅ Aligned | |

### 6.2 Stage Progress Bar

**Wireframe:**
```
│  │  ●━━━━━●━━━━━●━━━━━●━━━━━●━━━━━○━━━━━○━━━━━○━━━━━○━━━━━○                      │
│  │  New   Source S.Quo  B.Quo Negot  B.PO  S.PO  Fulfill Invoice Closed         │
│  │                      CURRENT STAGE: NEGOTIATION                               │
```

**Current:** Not implemented as visual progress bar.

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Visual progress bar | Horizontal step indicator | Badge only | ❌ Gap |
| Stage labels | Below dots | N/A | ❌ Gap |
| Current stage highlight | Filled vs hollow dots | N/A | ❌ Gap |

**Required Changes:**

Implement a custom Filament component or Blade view for stage progress:

```php
// In ViewRequest.php infolist
View::make('filament.components.request-stage-progress')
    ->viewData(['currentStage' => $record->stage]),
```

### 6.3 Financial Summary + Quick Actions

**Wireframe:**
```
│  ┌─────────────────────────────┐  ┌────────────────────────────────────────────┐
│  │    💰 FINANCIAL SUMMARY     │  │              QUICK ACTIONS                  │
│  │  Buyer Quote:    $9,912     │  │  [📄 Create Revised Quote]                  │
│  │  Supplier Costs: $8,150     │  │  [⏰ Extend Quote Validity]                  │
│  │  Gross Margin:   $780       │  │  [📧 Resend Quote to Buyer]                 │
│  │  Margin %:       8.7%       │  │  [✓ Mark Quote Accepted]                    │
│  └─────────────────────────────┘  │  [✗ Mark Quote Rejected]                    │
│                                   └────────────────────────────────────────────┘
```

**Current:** Financial data computed in model, but not displayed in this layout.

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Side-by-side cards | Financial + Actions | Tabs only, no summary | ❌ Gap |
| Buyer Quote total | "$9,912 (incl. 11% tax)" | Computed in model | ⚠️ Partial |
| Supplier Costs | Converted total | Computed in model | ⚠️ Partial |
| Gross Margin | Revenue - Cost | Computed in model | ⚠️ Partial |
| Margin % | Percentage | Computed in model | ⚠️ Partial |
| Quick Actions buttons | 5 contextual actions | In header ActionGroup | ❌ Gap |

**Required Changes:**

1. Create Financial Summary widget component
2. Create Quick Actions card with stage-aware buttons
3. Use Filament's Grid/Flex layout:

```php
Flex::make([
    Section::make('Financial Summary')
        ->schema([/* P&L stats */])
        ->grow(false),
    Section::make('Quick Actions')
        ->schema([/* Action buttons */])
        ->grow(false),
]),
```

### 6.4 Tabs Content Area

**Wireframe:**
```
│  ┌───────────────────────────────────────────────────────────────────────────────┐
│  │  [Items] [Suppliers (3)] [Buyer Quote★] [Orders] [Invoices] [Log]            │
```

**Current (`ViewRequest.php`):**

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Tabs | Items, Suppliers, Buyer Quote, Orders, Invoices, Log | Overview, Internal Notes, Activity | ❌ Gap |
| Tab badges | "Suppliers (3)" with count | None | ❌ Gap |
| Active indicator | ★ for current step | None | ❌ Gap |

**Required Changes:**

The current implementation uses RelationManagers at the page level. The wireframe expects:

1. **Single tabbed interface** within the view page with all sections
2. **Tab badges** showing counts
3. **Contextual highlighting** based on current stage

Consider refactoring to use Filament Tabs with embedded tables instead of separate RelationManagers:

```php
Tabs::make('RequestDetails')
    ->tabs([
        Tab::make('Items')
            ->badge(fn (Request $record) => $record->items()->count())
            ->schema([/* Items table */]),
        Tab::make('Supplier Quotes')
            ->badge(fn (Request $record) => $record->supplierQuotes()->count())
            ->schema([/* Supplier quotes content */]),
        // ...
    ])
```

---

## 7. Items Tab - Capture → Match Workflow

**Wireframe Reference:** Section 7.2 of uiux.md

### Wireframe Layout:
```
│  ⚠️ 2 of 3 items matched. Match all items before requesting supplier quotes.
│
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │  #1  "Tyre for Toyota Prius 2020, good brand"                               │
│  │      Qty: 4 pcs                                                             │
│  │      Article: Michelin Pilot Sport 215/45R17            ✓ Matched Jan 8     │
│  │      [View Article] [Change Match] [Remove]                                 │
│  └─────────────────────────────────────────────────────────────────────────────┘
```

### Current (`ItemsRelationManager.php`):

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Warning banner | "⚠️ 2 of 3 items matched..." | Not present | ❌ Gap |
| Card-style items | Individual cards per item | Table rows | ❌ Gap |
| Description prominent | Large quoted text | `description` column | ⚠️ Partial |
| Match status | "✓ Matched Jan 8" | Icon column | ⚠️ Partial |
| Action buttons | [View Article] [Change Match] [Remove] | Match/Unmatch actions | ⚠️ Partial |

**Required Changes:**

1. **Add warning banner** above table when not all items matched
2. **Consider card-style layout** for items (optional - table is acceptable)
3. **Add "View Article" action** to row actions
4. **Rename actions** to match wireframe labels

---

## 8. Match Article Modal

**Wireframe Reference:** Section 7.2.1 of uiux.md

### Wireframe Layout:
```
│  MATCH ARTICLE                                                               [✕]
│
│  Buyer requested: "Oil filter"
│  Qty: 1 pcs
│
│  Search existing articles:
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │ 🔍 oil filter...                                                            │
│  └─────────────────────────────────────────────────────────────────────────────┘
│
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │  ○ Toyota Genuine Oil Filter 90915-YZZD1                                    │
│  │    Categories: [Automotive] [Filters]                                       │
│  │                                                                              │
│  │  ● Mann Filter HU 7008 z                                    ← Selected      │
│  │    Categories: [Automotive] [Filters] [OEM]                                 │
│  └─────────────────────────────────────────────────────────────────────────────┘
│
│  ─── OR ───
│  [+ Create New Article]
│
│  [Cancel]  [✓ Match Article]
```

### Current (`ItemsRelationManager.php` match action):

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Modal title | "MATCH ARTICLE" | Default Filament action title | ⚠️ Partial |
| Context display | "Buyer requested: 'Oil filter'" | Not shown | ❌ Gap |
| Article search | Searchable with categories shown | Select with search | ⚠️ Partial |
| Category badges | Shown per article option | Not shown | ❌ Gap |
| Create new option | "[+ Create New Article]" | Not available | ❌ Gap |
| Footer buttons | [Cancel] [✓ Match Article] | Standard modal | ✅ Aligned |

**Required Changes:**

1. **Add context header** to match form:
   ```php
   Placeholder::make('context')
       ->content(fn ($record) => "Buyer requested: \"{$record->description}\"\nQty: {$record->quantity} {$record->unit}"),
   ```

2. **Enhance article select** with custom option display showing categories

3. **Add "Create New Article" option** inline or as separate button

---

## 9. Supplier Quotes Tab

**Wireframe Reference:** Section 7.3 of uiux.md

### Wireframe Shows:
- Category filter at top: `Find suppliers by categories: [Chemicals] [Industrial] [____] 🔍`
- Card-per-supplier layout with:
  - Header: `✓ SUPPLIER 1: MotorCorp Indonesia    SELECTED`
  - Categories badges
  - Line items table with currency conversion
  - Lead time and validity
  - [View] [Edit] [Deselect] actions
- Consolidated cost summary at bottom

### Current (`SupplierQuotesRelationManager.php`):

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Category filter | Tag-based filter | Not present | ❌ Gap |
| Card layout | Per-supplier cards | Table rows | ❌ Gap |
| Selection indicator | "✓ SELECTED" in header | Status column | ⚠️ Partial |
| Currency conversion | "In USD (@ 15,500): $5,371" | Not inline | ❌ Gap |
| Consolidated summary | Bottom card with totals | Not present | ❌ Gap |

**Required Changes:**

Major restructure needed to match wireframe - consider custom Livewire component instead of RelationManager.

---

## 10. Buyer Quote Tab

**Wireframe Reference:** Section 7.3 (Buyer Quote) of uiux.md

### Key Elements from Wireframe:

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Version history | `[v1 Draft] → [v2 Sent★]` | Version tracking exists | ⚠️ Partial |
| Validity section | Card with expiry warning + [Extend] | Basic date display | ❌ Gap |
| Internal vs Buyer view | Two table views shown | Not differentiated | ❌ Gap |
| Margin analysis card | Visual margin bar | Not present | ❌ Gap |
| Payment terms display | Card format | Basic text | ⚠️ Partial |

---

## 11. Quote Extension Modal

**Wireframe Reference:** Section 7.4 of uiux.md

### Wireframe Layout:
```
│  EXTEND QUOTE VALIDITY                                                       [✕]
│
│  Quote: Q-2024-0089-v2
│  Current Valid Until: Jan 22, 2024 (expires in 14 days)
│
│  New Valid Until *
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │ Feb 5, 2024                                                            📅   │
│  Extension: +14 days
│
│  Reason for Extension *
│  ┌─────────────────────────────────────────────────────────────────────────────┐
│  │ Buyer requested additional time for internal budget approval...             │
│
│  PRICE & AVAILABILITY CHECK
│  ○ No, prices remain the same
│  ● Yes, prices have changed
│
│  ⚠️ This extension will be logged in the request activity.
```

### Current Status:
Extension functionality exists but needs modal UI verification.

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Context header | Quote ref + current validity | Needs verification | ❓ Unknown |
| Extension duration calc | "+14 days" shown | Needs verification | ❓ Unknown |
| Price/availability check | Radio options | Likely missing | ❌ Gap |
| Activity log warning | "⚠️ This extension will be logged" | Needs verification | ❓ Unknown |

---

## 12. Invoice Detail & Credit Note Modals

**Wireframe Reference:** Sections 7.6.1, 7.6.2, 7.6.3 of uiux.md

### Invoice Detail Modal Elements:

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Line items table | Detailed with tax breakdown | Needs verification | ❓ Unknown |
| Payment history | Listed in modal | Likely as relation | ⚠️ Partial |
| Linked credit notes | Section in modal | Needs verification | ❓ Unknown |

### Credit Note Creation Modal:

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Item selection | Checkboxes with qty input | Needs verification | ❓ Unknown |
| Running total | Updates as items selected | Needs verification | ❓ Unknown |
| New balance calc | Shows after-credit balance | Needs verification | ❓ Unknown |

---

## 13. Exchange Rate Management

**Wireframe Reference:** Section 7 of uiux.md

### Wireframe Shows:
- Today's rates table with From/To/Rate/Source
- Inverse rate display: "(1 USD = 15,500 IDR)"
- Rate history table

### Current (`ExchangeRateResource`):

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Inverse rate display | "(1 USD = 15,500 IDR)" | Needs verification | ❓ Unknown |
| Rate history | Per-pair history table | Likely not implemented | ❌ Gap |
| "Today's Rates" heading | Grouped by date | Standard table | ⚠️ Partial |

---

## 14. Activity Log Tab

**Wireframe Reference:** Section 8 of uiux.md

### Wireframe Layout:
```
│  ACTIVITY LOG                                          Filter: [All Types ▼]
│
│  TODAY
│  ──────
│  02:30 PM  ⏰ Quote validity extended                            admin
│            Q-2024-0089-v2 extended from Jan 22 → Feb 5 (+14 days)
│            Reason: "Buyer requested additional time..."
│            ⚠️ Prices changed: Yes  │  Availability changed: No
```

### Current Implementation:

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Grouped by date | "TODAY", "YESTERDAY" | Needs verification | ❓ Unknown |
| Activity icons | ⏰ 📦 💰 per type | Needs verification | ❓ Unknown |
| Inline details | Reason text, metadata | Needs verification | ❓ Unknown |
| Filter dropdown | [All Types ▼] | Needs verification | ❓ Unknown |

---

## 15. System Audit Log (Admin)

**Wireframe Reference:** Section 9 of uiux.md

### Current (`AuditLogResource` if exists):

| Element | Wireframe | Current | Status |
|---------|-----------|---------|--------|
| Filter panel | Date range + Entity + User + Action | Needs verification | ❓ Unknown |
| Export CSV | Button in header | Needs verification | ❓ Unknown |
| Audit detail modal | Side-by-side old/new values | Needs verification | ❓ Unknown |

---

## Priority Action Items

### P0 - Critical (Layout Structure)

1. **Request Detail Command Center** - Complete restructure needed:
   - Add stage progress bar component
   - Add financial summary + quick actions side-by-side
   - Restructure tabs to match wireframe

2. **Navigation Groups** - Rename to match wireframe hierarchy

### P1 - High (Missing Features)

3. **Categories (Tags)** - Add to Article form with inline create
4. **Items Tab** - Add warning banner, enhance match modal
5. **Supplier Quotes Tab** - Add consolidated cost summary

### P2 - Medium (UI Polish)

6. **TagResource** - Rename to "Categories", add count columns
7. **Quote Extension Modal** - Add price/availability check
8. **Activity Log** - Date grouping, icons, inline details

### P3 - Low (Nice to Have)

9. **Exchange Rate History** - Per-pair history table
10. **Invoice Detail Modal** - Enhanced line item display
11. **Credit Note Modal** - Interactive item selection with totals

---

## Implementation Approach

For each gap, use the composition hierarchy from `.claude/skills/ui-components/SKILL.md`:

```
1. Filament Component  →  Use if available
2. Blade Component     →  Use if exists in resources/views/components/
3. Compose Existing    →  Combine existing components
4. Raw Tailwind        →  Only when nothing above fits
```

### Filament Components to Use:

| Wireframe Element | Filament Component |
|-------------------|-------------------|
| Page sections | `Section::make()->description()` |
| Warning banners | `Placeholder` with custom styling or `Notification` |
| Progress bar | Custom Blade component |
| Side-by-side cards | `Flex::make()` or grid layout |
| Tabs with badges | `Tab::make()->badge()` |
| Modal forms | Action forms with `modalHeading()` |
| Stat cards | `Stat::make()` in widgets |
| Card layouts | `Section::make()` per card |

---

## Files Requiring Changes

| File | Changes Required |
|------|------------------|
| `TagResource.php` | Rename, add count columns |
| `ArticleResource.php` | Add tags field, reorder sections |
| `SupplierResource.php` | Reorganize sections, add helper text |
| `ViewRequest.php` | Major restructure for command center |
| `ItemsRelationManager.php` | Add warning, enhance match modal |
| `SupplierQuotesRelationManager.php` | Add summary card |
| `BuyerQuotesRelationManager.php` | Add version display, validity card |
| `RequestResource.php` | Update navigation sort |
| `AdminPanelProvider.php` | Update navigation groups |
| `RequiresAttentionWidget.php` | Add inline action buttons |
| New: `StageProgressComponent.php` | Custom progress bar |
| New: `FinancialSummaryWidget.php` | P&L display card |
