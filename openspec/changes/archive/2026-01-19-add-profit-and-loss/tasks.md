# Tasks: Add Profit and Loss

## 1. Database - Profit and Losses
- [x] 1.1 Create migration for `profit_and_losses` table
  - Fields: id, team_id, request_id, buyer_quote_id, pnl_number, description, pnl_date
  - Foreign keys: prepared_by_id, creator_id
  - Text fields: dept_head_sales_name, deputy_director_name, approved_by_name
  - JSON: data (for future expansion)
  - Timestamps
- [x] 1.2 Create `ProfitAndLoss` model with relationships
- [x] 1.3 Add PNL number generation helper (4-digit increment, roman numerals for months)

## 2. Authorization
- [x] 2.1 Create `ProfitAndLossPolicy` with standard CRUD methods
- [x] 2.2 Add PNL permissions to `ErpPermissionSeeder`
  - view profit and losses
  - create profit and losses
  - update profit and losses
  - delete profit and losses
- [x] 2.3 Assign permissions to admin and sales roles
- [x] 2.4 Run permission seeder

## 3. Profit and Loss Resource
- [x] 3.1 Create `ProfitAndLossResource` in Master Data section
- [x] 3.2 Create List page with columns:
  - PNL Number, Status, Request Number, Description, Date, Created By
- [x] 3.3 Create View page with sections:
  - PNL Information (number, date, request link, status, description)
  - Selected Items by Supplier (buyer quote items grouped by supplier)
  - Central Purchasing section (4 columns)
  - Created by / timestamp footer
- [x] 3.4 Create Edit page for description and Central Purchasing fields
- [x] 3.5 Add Download PDF button to View page

## 4. UI Integration
- [x] 4.1 Add "Create PNL" button to BuyerQuotesRelationManager header actions
- [x] 4.2 Button only visible when buyer quotes exist for the request
- [x] 4.3 Implement modal form with:
  - PNL Information section (number placeholder, date, request, description)
  - Central Purchasing section (prepared_by select, text inputs for others)
- [x] 4.4 Implement save functionality:
  - Generate PNL number
  - Link to latest valid buyer quote (excluding rejected/superseded)
  - Save to database
  - Show success notification
  - Redirect to PNL view page

## 5. PNL Status
- [x] 5.1 Create `PnlStatus` enum (Pending/Ordered)
- [x] 5.2 Add computed `status` attribute to ProfitAndLoss model
  - Pending: No buyer orders exist for the request
  - Ordered: Buyer orders exist for the request
- [x] 5.3 Display status badge in list and view pages

## 6. Selected Items by Supplier
- [x] 6.1 Create blade view `pnl-selected-items.blade.php`
- [x] 6.2 Display buyer quote items grouped by supplier
- [x] 6.3 Show per-supplier: header with name, cost/sell/margin totals
- [x] 6.4 Show per-item: Item, Qty, Cost, Sell, Tax, Margin %, Line Total
- [x] 6.5 Add section to PNL view page

## 7. PDF Generation
- [x] 7.1 Create `profit-and-loss.blade.php` PDF template
- [x] 7.2 Include PNL information header
- [x] 7.3 Include items by supplier with totals
- [x] 7.4 Include grand total section
- [x] 7.5 Include Central Purchasing approval section with signature lines
- [x] 7.6 Add Download PDF action to ViewProfitAndLoss page
