# ERPC - Enterprise Trading Platform

A modern ERP system built for trading companies, combining CRM capabilities with comprehensive procurement and sales workflows.

## Overview

ERPC is designed for trading businesses that source products from multiple suppliers and sell to buyers. It manages the complete lifecycle from buyer inquiry through supplier sourcing, quoting, ordering, invoicing, and payment tracking.

The platform runs as multiple Filament panels on shared infrastructure:

- **App panel** (`app.{domain}`) — internal team workspace for Central Purchasing and operations
- **Buyer portal** (`BUYER_PATH`, default `buyer`) — buyer self-service for requests, quotes, invoices, and shipments
- **Supplier portal** (`SUPPLIER_PATH`, default `supplier`) — supplier request responses and article pricing
- **Public catalog** (`/`) — storefront for published articles and quote-cart submissions
- **System Admin** (`SYSADMIN_PATH`, default `sysadmin`) — cross-tenant administration module

## Tech Stack

- **PHP 8.4** with strict types
- **Laravel 12** framework
- **Filament 5** admin panel (app, customer, supplier, and system-admin panels)
- **Livewire 4** for reactive components
- **PostgreSQL 15+** database
- **Tailwind CSS 4** styling
- **Laravel Horizon** for queue monitoring
- **Spatie** packages: permissions, media library, activity log, data objects, settings, custom fields

## Key Features

### Trading Workflow
- **Request Management** - Track buyer inquiries from initial request through fulfillment
  - **Request View page** - Single request view (`RequestResource::view`) with tabbed relation managers: Requested Items, Supplier Quotes, Buyer Quotes, Purchases (Supplier Orders), Goods Receive, Invoices (Buyer Orders), Fulfillment (Shipments + Acceptance Reports), Completion Report. Stage advancement and tab access follow `RequestStage` and approval rules (QE, P&L, supplier order approval).
  - **Information Flow widget** - Step-by-step guide shown at the **bottom of the Request View page** (footer widget). Content is **per-tab**: when a tab is selected, the widget shows that step's flow as a **bulleted list**. Steps 1–8: (1) Requested Items, (2) Supplier Quotes, (3) Buyer Quotes, (4) Purchases, (5) Goods Receive, (6) Invoices, (7) Fulfillment (goods shipments and service acceptance reports), (8) Completion Report. Implemented in `App\Filament\Widgets\RequestInformationFlowWidget` and view `resources/views/filament/widgets/request-information-flow-widget.blade.php`; registered via `ViewRequest::getFooterWidgets()`.
  - **Submission sources** - Requests can originate from the internal app, buyer portal, supplier portal, or public catalog (`RequestSubmissionMethod`).
  - **Activity timeline** - Footer widget on Request View (`RequestHistoryWidget` → `RequestHistoryTimeline` Livewire) shows chronological audit changes, uploads, credit movements, and milestones; paginated with drill-in to change detail
  - **Request notes** - `RequestNoteComposer` pinned below the timeline; staff choose visibility (internal, to buyer, to supplier); buyers and suppliers post notes scoped to their party; supports file attachments
- **Supplier Quoting** - Collect and compare quotes from multiple suppliers; send quote requests to suppliers via email and the supplier portal
- **Quotation Evaluation** - Generate internal QE documents with item comparison, supplier info, and approval workflow
  - **Document upload** - Upload supporting documents on the QE view page (action group: Edit, Download PDF, Upload Document). Documents appear in a Documents section and in **Credit Limit Acceptances** for key account approval; once approved there, the QE status is set to Approved.
- **Buyer Quoting** - Generate consolidated quotes with margin analysis
  - **Payment terms and prepayment** - When the buyer has credit status, payment terms (installments) are validated so the total equals 100%. If **Prepayment type** is **Percentage** and a prepayment value is set, validation requires prepayment % + sum(payment term %) = 100%; if **Fixed Amount** or no prepayment, only the payment term percentages must sum to 100%. Validation runs on create and edit in `BuyerQuotesRelationManager` (`validatePaymentTermsTotal`). On edit, the Prepayment field is filled from `prepayment_percent` when type is Percentage (e.g. after copying from a single supplier quote on create), otherwise from `prepayment_amount`.
  - **Service items: +Tax sync** - For quotes with detail (child) items, the main item's **+ Tax** checkbox syncs to all child items: checking or unchecking the main item updates each child's + Tax and recalculates child line totals (`line_subtotal`, `line_tax`, `line_total`) so the child Line Total reflects tax when + Tax is checked. Implemented via main item `is_tax_inclusive` `afterStateUpdated`/`afterStateHydrated` and `syncChildItemLineTotals()` in `BuyerQuotesRelationManager`.
  - **Buyer PO Upload** - Upload and view buyer purchase order files via action button (available when quote status is Accepted)
  - **Expired quote handling** - When a buyer quote's valid-until date has passed: (1) View Quote modal shows a clear "This quote has expired" alert; (2) a daily job (`CheckExpiredQuotesJob`, 08:30) finds quotes that expired the previous day and sends an email to the buyer and to each key account assigned to that buyer; key accounts also receive an in-app notification. Each quote is notified only once (tracked via `notification_metadata`).
- **Profit & Loss** - Generate PNL documents with items by supplier, cost/sell/margin analysis, and approval workflow
  - **Document upload** - Upload supporting documents on the PNL view page (action group: Edit, Download PDF, Upload Document). Documents appear in a Documents section and in **Credit Limit Acceptances** for key account approval; once approved there, the PNL status is set to Approved.
- **Order Processing** - Manage buyer and supplier purchase orders
  - **Supplier Order Approval** - Dual-approval workflow requiring minimum 2 approvals from senior roles (Dept Head of Sales, Deputy Director, Director) before supplier orders can be sent
  - **Document upload** - Upload supporting documents from the Supplier Order Approval list (row action: Upload Document) or from the order view page (action group: Edit, Download PDF, Upload Document). Documents appear in a Documents section on the view page and in **Credit Limit Acceptances**; when a key account approves a document there, the supplier order status is set to Approved.
- **Goods Receive** - Upload delivery documents per supplier order; all documents must be approved via **Approval > Goods Receive** before fulfillment unlocks
- **Invoicing** - Issue real buyer invoices from confirmed orders, record payments, and track overdue status:
  - **Issue Invoice** — On the Request View **Invoices** tab (`BuyerOrdersRelationManager`), staff issue a `BuyerInvoice` from a confirmed buyer order via row action; copies order line items, sets `issued_at` / `due_at` (net days from order), emails the buyer (`InvoiceToBuyerMail`, template `resources/views/emails/invoice-to-buyer.blade.php`), and exposes PDF download
  - **Record Payment** — Staff record full or partial payments against the active invoice (amount, method, date, reference, proof upload); confirmed payments update invoice status and **release buyer credit** reserved at order confirmation
  - **Payments card** — Request View and buyer portal request pages show a payment-terms matrix with per-term pay buttons (`InteractsWithPaymentCard`); buyers submit payments as **Pending** until staff confirm
  - **Overdue tracking** — `CheckOverdueInvoicesJob` (09:00) marks overdue invoices and notifies the creator
- **Fulfillment** - Combined tab for goods and services:
  - **Shipments** - Monitor delivery status and logistics with Delivery Order (DO) PDF generation for inbound shipments
    - **PIC Contact** - On Create Shipment (Additional Info section): select a Person In Charge from the buyer's People/Contacts, or add a new person via the + button (reuses the full Create Person form: name, phone, email, Companies). New persons are attached to the buyer and shown in the PIC list. Shipment stores `pic_contact_id`. The Delivery Order PDF (`resources/views/pdf/shipment-delivery-order.blade.php`) displays PIC name and phone under the delivery address; `PdfGenerationService::generateShipmentDeliveryOrderPdf()` eager-loads `picContact`.
  - **Acceptance Reports** - Service-item completion reports filed within the Fulfillment tab (also manageable as a relation manager on the request view)
- **Completion Reports** - Final project documentation after delivery

### Public Catalog
- **Storefront** - When `CATALOG_ENABLED=true` (default), the marketing homepage is replaced by a public article catalog at `/` (`CatalogHome` Livewire component)
- **Article publishing** - Articles can be published to the catalog via **Show in Catalog** and **List Price** on the article form (`ArticleResource`); only active, published articles appear in the grid
- **Price review** - Daily `articles:refresh-price-review` job (07:00) flags articles whose list price may need review after FX drift or supplier cost changes; flagged articles surface for suppliers in the portal article list
- **Quote cart** - Buyers add catalog articles to a session quote cart (`/quote-cart`) and submit to create a portal-originated request (`SubmitQuoteCart`)
- **Registration** - Public registration form (`/registration`) creates pending portal registration requests for team approval
- **Configuration** - `config/catalog.php`: `CATALOG_ENABLED`, `CATALOG_TEAM_ID` (defaults to first team when unset)

### Buyer Portal
- **Panel** - Filament buyer panel at `BUYER_PATH` (default `buyer`) on `BUYER_DOMAIN` or the app subdomain; separate session cookie from the internal panel
- **Requests** - Buyers create and track requests (`BuyerRequestResource`); request view includes:
  - **Progress timeline** — Seven customer-journey milestones via `RequestStageTimelinePresenter` (effective stage reflects sent quotes and post-confirmation progress)
  - **Quotes** — Embedded buyer quotes relation manager; accept/reject sent quotes inline
  - **Payments** — Payment-terms card when a standard invoice exists; buyers submit payment proof as **Pending** for staff confirmation
  - **Activities** — Buyer-scoped activity timeline (`PortalTimelineSource`) with redacted milestones (no supplier costs, P&L, or internal staff names)
- **Invoices** - `InvoicesRelationManager` lists issued (non-draft) invoices with totals, paid amount, due date, and PDF download; `BuyerInvoiceStatusPresenter` maps **Sent** to buyer-facing label **Received**
- **Shipments** - Relation manager for outbound shipment tracking
- **Portal users** - Invited and managed from **Master Data > Buyers > Portal Users** (`PortalUsersRelationManager`); invitation lifecycle: Invited, Active, Deactivated
- **Registration approval** - Public registrations appear in **Approval > Registrations** (`PortalRegistrationRequestResource`) for approve/reject
- **Kill switch** - `BUYER_PORTAL_ENABLED=false` disables the panel

### Supplier Portal
- **Panel** - Filament supplier panel at `SUPPLIER_PATH` (default `supplier`) on `SUPPLIER_DOMAIN` or the app subdomain; separate session cookie
- **Requests** - Suppliers view and respond to quote requests sent by the team (`SupplierRequestResource`); confidentiality enforced via query scope, policy, and column projection
- **My Articles** - Suppliers maintain offer pricing for articles linked to their company (`SupplierArticleResource`)
- **Portal users** - Invited and managed from **Master Data > Suppliers > Portal Users**
- **Kill switch** - `SUPPLIER_PORTAL_ENABLED=false` disables the panel

### Buyer Credit Management
- **Credit Limits** - **Finance > Credit Limits** overview per buyer: max credit limit, available credit, credit used, and usage history (`BuyerCreditLimitOverviewResource`)
- **Credit Limit Requests** - Buyers (or key accounts) can request limit increases; dual-approval workflow in **Approval > Credit Limit Requests** (`BuyerCreditLimitRequestResource`)
- **Credit warnings** - `CreditLimitWarningService` warns when new orders would exceed a buyer's available credit during order creation
- **Credit lifecycle** - Credit is reserved when a buyer order is confirmed; released incrementally when invoice payments are confirmed (not only on order cancellation)

### Document storage (uploads)
- Uploaded documents (supplier quotes, buyer quotes, supplier orders, goods receive, completion reports, quotation evaluation, profit and loss) are stored in **dedicated folders per feature** under `storage/app/` (local disk).
- Path structure: `storage/app/{folder}/{media_id}/uploaded_document_files/` where `{folder}` is one of: `supplierquote`, `buyerquote`, `supplierorder`, `goodreceive`, `completionreports`, `attachments`, `quotationevaluation`, `profitandloss`. Each file has a unique **media id** (from the `media` table).
- **QE, PNL, and Supplier Order documents** - Each of these models has a `documents` media collection (Spatie Media Library). Documents uploaded on the QE/PNL/Supplier Order view pages (or from the Supplier Order Approval list row action) are attached to the record and listed in **Credit Limit Acceptances** for approval.
- Implemented via **DocumentPathGenerator** (`app/Support/Media/DocumentPathGenerator.php`), registered in `config/media-library.php` for the relevant models. Morph map aliases (e.g. `supplier_quote`, `quotation_evaluation`, `profit_and_loss`) are registered in `AppServiceProvider` so polymorphic media resolve correctly.
- **Backward compatible**: existing media on the `public` disk or without the `path_version` custom property keep the legacy path `{id}/`. New uploads use the dedicated path; supplier and buyer quote uploads set `path_version` so they use the new structure.

### Credit Limit Acceptances
- **Approval > Credit Limit Acceptances** lists documents that require approval:
  - **Payment documents** - Request completion reports marked as payment documents (approved by Central Purchasing **Finance** approvers).
  - **QE / PNL / Supplier Order documents** - Documents uploaded on QE, PNL, or Supplier Order view/list; approved by **Key Account** (Central Purchasing Key Account role).
- Table columns: Document Name, Source (e.g. `QE 008-DS/QE/II/2026`, `PNL …`, `PO PO-2026-0011`, or Payment Document), Request Number, Buyer, Payment Terms, Uploaded At, Status, Approved By, Approved At. Rows are not clickable; open document via row actions (View Document, Approve).
- **Source** links to the QE, PNL, or Supplier Order view page when applicable. **Request Number** links to the related Request or entity view.
- When a key account approves a QE/PNL/Supplier Order document, a **PaymentDocumentApproval** record is created and the related entity's status is set to **Approved** (via `approveViaDocumentAcceptance()` on the model).

### Core Entities
- **Buyers & Suppliers** - Separate master data resources (retired generic Company resource); each has contacts, portal users, and role-specific fields
- **Articles** - Product catalog with flexible attributes, supplier links, images, and public catalog pricing
- **Categories** - Tag-based categorization for articles and catalog navigation (`TagResource`, labeled "Categories")
- **Projects** - Group related requests for large deals
- **Currencies & Exchange Rates** - Multi-currency support
- **Tax Codes** - Configurable tax handling per item
- **Unit of Measures** - Standardized units for articles and line items
- **Team Members** - Central Purchasing personnel for approval workflows in QE, PNL documents, and Supplier Orders (managed as team members with Central Purchasing role)

### CRM Capabilities
- **People/Contacts** - Contact management linked to buyers and suppliers. People have **name**, **phone**, and **email**; the Create Person form includes these plus an optional **Companies** multi-select (with + to add companies). The same form is reused when adding a contact from the Create Shipment PIC field (Filament `actionSchemaModel(People::class)` so the Companies relationship resolves correctly).
- **AI Summaries** - AI-powered entity summaries for People and Companies (`RecordSummaryService`, Prism)

### Platform Features
- **Multi-Team** - Isolated workspaces per team (Jetstream tenancy)
- **Multi-panel domains** - `APP_PANEL_DOMAIN`, `BUYER_DOMAIN`, `SUPPLIER_DOMAIN`, and `SYSADMIN_DOMAIN`/`SYSADMIN_PATH` configure panel routing; see `App\Support\PanelDomain`
- **Navigation & menu access** - All team members (verified email + current team) can see sidebar menus in **Workflow**, **Master Data**, **Approval**, **Finance**, and **Settings**. Menu visibility is driven by policies' `viewAny()`, and where applicable by resource `shouldRegisterNavigation()` (e.g. Supplier Order Approvals, QE, P&L) or page `canAccess()`. Record-level permissions (view, create, update, delete on individual records) still follow policies and Spatie/team role permissions.
- **Team Member Roles** - Three role types:
  - **Administrator** - Full access to all features
  - **Editor** - Read, create, and update permissions
  - **Central Purchasing** - Read, create, and update permissions with hierarchical sub-roles:
    - Key Account (prepares QE/PNL documents; approves QE/PNL/Supplier Order uploaded documents via Credit Limit Acceptances)
    - Dept. Head of Sales (approval workflow for QE, PNL, and Supplier Orders)
    - Deputy Director (approval workflow for QE, PNL, and Supplier Orders)
    - Director (final approval for QE, PNL, and Supplier Orders)
- **Email Settings** - Comprehensive email configuration:
  - **Email Template Management** - Create, edit, and manage multiple email templates per document type:
    - Dedicated Email Templates page for template library management
    - Template selection via dropdown in Email Settings
    - Load default templates from blade files as starting points
    - Support for both simple content and full HTML email templates
    - Automatic fallback to default templates when custom templates are deleted
    - Template variables system for dynamic content ({{buyer_name}}, {{quote_number}}, etc.)
  - Team-specific SMTP configuration with encrypted passwords
  - Email branding with logo upload and signature
  - Per-template sender, CC, and BCC configuration (via Email Settings)
  - Test email functionality
- **Custom Fields** - Extend entities without code changes (Relaticle Custom Fields plugin)
- **Role-Based Access** - Granular permissions via Spatie Laravel Permission
- **Import/Export** - CSV/Excel data management:
  - **Import / Export** button on list pages (gray header button, same pattern as People): Buyers, Suppliers, Articles, Quotation Evaluations, Profit & Loss, Buyer Quotes, Buyer Orders, Supplier Quotes, Supplier Orders
  - **Import + Export** (master data): People, Buyers, Suppliers, Articles (column mapping, optional custom fields)
  - **Export only** (transactional/report data): Quotation Evaluations, Profit & Loss, Buyer Quotes, Buyer Orders, Supplier Quotes, Supplier Orders
  - Export completion notifications include download links (CSV/XLSX); custom `ExportCompletion` job refreshes the Export model from the DB so links appear correctly when using the queue
  - Implementation: `app/Filament/Exports/` (exporters), `app/Filament/Imports/` (importers), list page `getHeaderActions()` and table `ExportBulkAction` on each resource
- **Scheduled jobs** (`routes/console.php`):
  - **Price review** (07:00) – `articles:refresh-price-review`: recompute `price_review_needed` on published catalog articles
  - **Expiring quotes** (08:00) – `CheckExpiringQuotesJob`: notifies quote creator when a buyer quote is expiring in 7, 3, or 1 day(s)
  - **Expired quotes** (08:30) – `CheckExpiredQuotesJob`: for quotes that expired the previous day, emails the buyer and key accounts (`QuoteExpiredMail`), and sends key accounts an in-app notification (`QuoteExpiredNotification`)
  - **Overdue invoices** (09:00) – `CheckOverdueInvoicesJob`: marks overdue buyer invoices and notifies the creator (`InvoiceOverdueNotification`)
  - **Awaiting supplier quotes** (10:00) – `CheckAwaitingSupplierQuotesJob`: alerts when supplier quotes have been awaiting response for more than 7 days

## Requirements

- PHP 8.4+
- PostgreSQL 15+
- Composer 2
- Node.js 20+
- Redis (recommended for cache and queues)

## Installation

```bash
git clone <repository-url>
cd erpc
composer app-install
```

Copy `.env.example` to `.env` and configure database, Redis, mail, and panel domains as needed.

## Configuration

| Variable | Default | Purpose |
| -------- | ------- | ------- |
| `APP_URL` | `http://localhost` | Public site URL (catalog, auth redirects) |
| `APP_PANEL_DOMAIN` | `app.{APP_URL host}` | Internal app panel subdomain |
| `BUYER_PATH` | `buyer` | Buyer portal path prefix |
| `BUYER_DOMAIN` | (app domain) | Optional dedicated buyer portal domain |
| `BUYER_PORTAL_ENABLED` | `true` | Kill switch for buyer portal |
| `SUPPLIER_PATH` | `supplier` | Supplier portal path prefix |
| `SUPPLIER_DOMAIN` | (app domain) | Optional dedicated supplier portal domain |
| `SUPPLIER_PORTAL_ENABLED` | `true` | Kill switch for supplier portal |
| `SYSADMIN_PATH` | `sysadmin` | System admin panel path |
| `CATALOG_ENABLED` | `true` | `false` restores static marketing homepage |
| `CATALOG_TEAM_ID` | (first team) | Team whose articles appear in the public catalog |

## Development

```bash
# Start all services (server, queue, logs, vite)
composer dev

# Run tests
composer test

# Format code
composer lint

# Type checking
composer test:types
```

## Testing

```bash
composer test          # Full test suite
composer test:arch     # Architecture tests
composer test:types    # PHPStan static analysis
composer test:coverage # Code coverage (min 80%)
```

## Project Structure

```
app/
├── Actions/              # Single-purpose action classes
│   ├── Catalog/          # Quote cart submission, price review
│   ├── BuyerPortal/      # Portal registration, request notifications
│   ├── Portal/           # Portal user invitations
│   └── SupplierPortal/   # request submit/decline, article offers
├── Data/                 # Data transfer objects (Spatie Laravel Data)
├── Enums/                # PHP enums
├── Filament/             # Admin panel resources
│   ├── Buyer/            # Buyer portal resources and pages
│   ├── Concerns/         # InteractsWithPaymentCard (shared payment UI)
│   ├── Supplier/         # Supplier portal resources, pages, widgets
│   ├── Exports/          # Exporters; Jobs/ExportCompletion (refresh before notification)
│   ├── Imports/          # Importers for master data
│   ├── Pages/            # Custom pages (EmailSettings, Settings, ApiTokens)
│   ├── Resources/        # App panel resources (Requests, Buyers, Approval, Finance, etc.)
│   └── Widgets/          # RequestInformationFlowWidget, RequestHistoryWidget
├── Http/
│   ├── Controllers/      # Document downloads, PDFs, auth callbacks
│   └── Middleware/       # Tenant scopes, panel sessions, portal context
├── Jobs/                 # Background jobs (Erp alerts, favicon fetch, email subscribers)
├── Livewire/
│   ├── Catalog/          # Public catalog, quote cart, registration
│   ├── RequestHistoryTimeline.php  # Per-request activity feed
│   └── RequestNoteComposer.php     # Timeline note + attachment composer
├── Mail/                 # Laravel Mailables (Erp/, portal invitations)
├── Notifications/        # Laravel Notifications (Erp/)
├── Models/               # Eloquent models
├── Observers/            # Model observers
├── Policies/             # Authorization policies
├── Providers/
│   └── Filament/         # AppPanelProvider, BuyerPanelProvider, SupplierPanelProvider
├── Services/
│   ├── AI/               # Record summary generation
│   ├── Catalog/          # Quote cart, article cost resolution, team resolver
│   ├── BuyerPortal/      # Request stage + invoice status presentation
│   ├── Email/            # Email template and SMTP services
│   ├── Erp/              # PDF generation, tax, credit limits, financial totals
│   ├── Portal/           # Buyer/supplier portal context, stage timeline presenter
│   ├── Timeline/         # Audience-scoped activity feed, redaction rules
│   └── SupplierPortal/   # request status presentation
└── Support/
    ├── Media/            # DocumentPathGenerator (Spatie Media Library path per feature)
    └── PanelDomain.php   # Multi-panel host resolution

app-modules/              # Isolated modules
├── Documentation/        # In-app documentation site
├── OnboardSeed/          # Demo/seed fixtures
└── SystemAdmin/          # Cross-tenant system administration panel

openspec/                 # Specifications
└── specs/                # Feature specifications
```

## License

AGPL-3.0
