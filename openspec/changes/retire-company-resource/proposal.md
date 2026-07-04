# Retire Company Resource — Consolidate on Buyers/Suppliers

## Why

The app inherits a generic "Companies" resource from the Relaticle CRM base, sitting in the Workspace navigation group alongside Buyers and Suppliers (both filtered views of the same `Company` model in Master Data). Three navigation entries over one table confuse users: buyer lists and company lists look nearly identical, and Companies is the only place to toggle `is_buyer`/`is_supplier` roles. The ERP domain vocabulary is Buyers and Suppliers; a role-less "Company" has no meaning in the trading workflow.

## What Changes

- **Delete `CompanyResource`** and its pages entirely. Buyers and Suppliers become the only company management surfaces.
- **Require a role at company creation everywhere.** Every company must be a buyer, a supplier, or both. The inline company-create form on People gains buyer/supplier checkboxes with an at-least-one-required rule.
- **Cross-role toggles.** Buyer form gains an "Also a supplier" checkbox; Supplier form gains "Also a buyer". A dual-role company appears in both lists and is editable from either (same record).
- **Retarget references** (~10 files): login redirect and panel home URL point to Buyers instead of Companies; record links in People/Opportunity views and Member/Article relation managers point to Buyer/Supplier views; the shared company form schema moves out of `CompanyResource` into a standalone schema class consumed by Buyers, Suppliers, and the People inline form.
- **Navigation regrouping.** The Workspace group is removed. People, Notes, Tasks, Opportunities resources plus the TasksBoard and OpportunitiesBoard pages move to Master Data.
- **Demo data cleanup.** The four role-less OnboardSeed demo companies (Airbnb, Apple, Figma, Notion) are deleted from existing teams; OnboardSeed fixtures are updated so seeded companies always carry a role.
- **Unchanged:** credit fields stay read-only on the Buyer form (changes flow through the credit-limit approval workflow); soft-delete/restore continues to work from the Buyer/Supplier lists.

## Impact

- Affected specs: `crm-core` (Company Management removed, People Management modified), `erp-trading-core` (new Company Role Classification requirement; Buyers/Suppliers requirements unchanged — new behavior is added as an orthogonal requirement rather than rewriting them)
- Affected code: `app/Filament/Resources/CompanyResource*` (deleted), `BuyerResource`, `SupplierResource`, `PeopleResource`, `ViewPeople`, `ViewOpportunity`, `MemberResource/RelationManagers/BuyersRelationManager`, `ArticleResource/RelationManagers/SuppliersRelationManager`, `app/Http/Responses/LoginResponse.php`, `app/Providers/Filament/AppPanelProvider.php`, `NoteResource`, `TaskResource`, `OpportunityResource`, `app/Filament/Pages/TasksBoard.php`, `app/Filament/Pages/OpportunitiesBoard.php`, `app-modules/OnboardSeed` fixtures, `CompanyExporter` wiring, related tests
- Data: one-off cleanup of role-less demo companies; no schema migration required (`is_buyer`/`is_supplier` columns already exist)
