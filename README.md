# ERPC - Enterprise Trading Platform

A modern ERP system built for trading companies, combining CRM capabilities with comprehensive procurement and sales workflows.

## Overview

ERPC is designed for trading businesses that source products from multiple suppliers and sell to buyers. It manages the complete lifecycle from buyer inquiry through supplier sourcing, quoting, ordering, invoicing, and payment tracking.

## Tech Stack

- **PHP 8.4** with strict types
- **Laravel 12** framework
- **Filament 5** admin panel
- **Livewire 4** for reactive components
- **PostgreSQL 15+** database
- **Tailwind CSS 4** styling

## Key Features

### Trading Workflow
- **Request Management** - Track buyer inquiries from initial request through fulfillment
  - **Request View page** - Single request view (`RequestResource::view`) with tabbed relation managers: Requested Items, Supplier Quotes, Buyer Quotes, Purchases (Supplier Orders), Goods Receive, Invoices (Buyer Orders), Inbound Shipments, Completion Report. Stage advancement and tab access follow `RequestStage` and approval rules (QE, P&L, supplier order approval).
  - **Information Flow widget** - Step-by-step guide shown at the **bottom of the Request View page** (footer widget). Content is **per-tab**: when a tab is selected, the widget shows that step’s flow as a **bulleted list**. Steps 1–8: (1) Requested Items, (2) Supplier Quotes, (3) Buyer Quotes, (4) Purchases, (5) Goods Receive, (6) Invoices, (7) Inbound Shipments, (8) Completion Report. Implemented in `App\Filament\Widgets\RequestInformationFlowWidget` and view `resources/views/filament/widgets/request-information-flow-widget.blade.php`; registered via `ViewRequest::getFooterWidgets()`.
- **Supplier Quoting** - Collect and compare quotes from multiple suppliers
- **Quotation Evaluation** - Generate internal QE documents with item comparison, supplier info, and approval workflow
  - **Document upload** - Upload supporting documents on the QE view page (action group: Edit, Download PDF, Upload Document). Documents appear in a Documents section and in the **Acceptance Report** for key account approval; once approved there, the QE status is set to Approved.
- **Buyer Quoting** - Generate consolidated quotes with margin analysis
  - **Buyer PO Upload** - Upload and view buyer purchase order files via action button (available when quote status is Accepted)
  - **Expired quote handling** - When a buyer quote’s valid-until date has passed: (1) View Quote modal shows a clear “This quote has expired” alert; (2) a daily job (`CheckExpiredQuotesJob`, 08:30) finds quotes that expired the previous day and sends an email to the buyer and to each key account assigned to that buyer; key accounts also receive an in-app notification. Each quote is notified only once (tracked via `notification_metadata`).
- **Profit & Loss** - Generate PNL documents with items by supplier, cost/sell/margin analysis, and approval workflow
  - **Document upload** - Upload supporting documents on the PNL view page (action group: Edit, Download PDF, Upload Document). Documents appear in a Documents section and in the **Acceptance Report** for key account approval; once approved there, the PNL status is set to Approved.
- **Order Processing** - Manage buyer and supplier purchase orders
  - **Supplier Order Approval** - Dual-approval workflow requiring minimum 2 approvals from senior roles (Dept Head of Sales, Deputy Director, Director) before supplier orders can be sent
  - **Document upload** - Upload supporting documents from the Supplier Order Approval list (row action: Upload Document) or from the order view page (action group: Edit, Download PDF, Upload Document). Documents appear in a Documents section on the view page and in the **Acceptance Report**; when a key account approves a document there, the supplier order status is set to Approved.
- **Invoicing** - Handle buyer and supplier invoices with payment tracking
- **Shipment Tracking** - Monitor delivery status and logistics with Delivery Order (DO) PDF generation for inbound shipments
  - **PIC Contact** - On Create Shipment (Additional Info section): select a Person In Charge from the buyer’s People/Contacts, or add a new person via the + button (reuses the full Create Person form: name, phone, email, Companies). New persons are attached to the buyer and shown in the PIC list. Shipment stores `pic_contact_id`. The Delivery Order PDF (`resources/views/pdf/shipment-delivery-order.blade.php`) displays PIC name and phone under the delivery address; `PdfGenerationService::generateShipmentDeliveryOrderPdf()` eager-loads `picContact`.

### Document storage (uploads)
- Uploaded documents (supplier quotes, buyer quotes, supplier orders, goods receive, completion reports, quotation evaluation, profit and loss) are stored in **dedicated folders per feature** under `storage/app/` (local disk).
- Path structure: `storage/app/{folder}/{media_id}/uploaded_document_files/` where `{folder}` is one of: `supplierquote`, `buyerquote`, `supplierorder`, `goodreceive`, `completionreports`, `attachments`, `quotationevaluation`, `profitandloss`. Each file has a unique **media id** (from the `media` table).
- **QE, PNL, and Supplier Order documents** - Each of these models has a `documents` media collection (Spatie Media Library). Documents uploaded on the QE/PNL/Supplier Order view pages (or from the Supplier Order Approval list row action) are attached to the record and listed in the **Acceptance Report** for approval.
- Implemented via **DocumentPathGenerator** (`app/Support/Media/DocumentPathGenerator.php`), registered in `config/media-library.php` for the relevant models. Morph map aliases (e.g. `supplier_quote`, `quotation_evaluation`, `profit_and_loss`) are registered in `AppServiceProvider` so polymorphic media resolve correctly.
- **Backward compatible**: existing media on the `public` disk or without the `path_version` custom property keep the legacy path `{id}/`. New uploads use the dedicated path; supplier and buyer quote uploads set `path_version` so they use the new structure.

### Acceptance Report
- **Approval > Acceptance Report** lists documents that require approval:
  - **Payment documents** - Request completion reports marked as payment documents (approved by Central Purchasing **Finance** approvers).
  - **QE / PNL / Supplier Order documents** - Documents uploaded on QE, PNL, or Supplier Order view/list; approved by **Key Account** (Central Purchasing Key Account role).
- Table columns: Document Name, Source (e.g. `QE 008-DS/QE/II/2026`, `PNL …`, `PO PO-2026-0011`, or Payment Document), Request Number, Buyer, Payment Terms, Uploaded At, Status, Approved By, Approved At. Rows are not clickable; open document via row actions (View Document, Approve).
- **Source** links to the QE, PNL, or Supplier Order view page when applicable. **Request Number** links to the related Request or entity view.
- When a key account approves a QE/PNL/Supplier Order document, a **PaymentDocumentApproval** record is created and the related entity’s status is set to **Approved** (via `approveViaDocumentAcceptance()` on the model).

### Core Entities
- **Companies** - Buyers and suppliers with contacts
- **Articles** - Product catalog with flexible attributes
- **Projects** - Group related requests for large deals
- **Currencies & Exchange Rates** - Multi-currency support
- **Tax Codes** - Configurable tax handling per item
- **Team Members** - Central Purchasing personnel for approval workflows in QE, PNL documents, and Supplier Orders (managed as team members with Central Purchasing role)

### CRM Capabilities
- **People/Contacts** - Contact management linked to companies. People have **name**, **phone**, and **email**; the Create Person form includes these plus an optional **Companies** multi-select (with + to add companies). The same form is reused when adding a contact from the Create Shipment PIC field (Filament `actionSchemaModel(People::class)` so the Companies relationship resolves correctly).
- **Opportunities** - Sales pipeline tracking
- **Tasks & Notes** - Activity management
- **AI Summaries** - AI-powered entity summaries

### Platform Features
- **Multi-Team** - Isolated workspaces per team
- **Team Member Roles** - Three role types:
  - **Administrator** - Full access to all features
  - **Editor** - Read, create, and update permissions
  - **Central Purchasing** - Read, create, and update permissions with hierarchical sub-roles:
    - Key Account (prepares QE/PNL documents; approves QE/PNL/Supplier Order uploaded documents via Acceptance Report)
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
- **Custom Fields** - Extend entities without code changes
- **Role-Based Access** - Granular permissions
- **Import/Export** - CSV/Excel data management:
  - **Import / Export** button on list pages (gray header button, same pattern as People): Buyers, Suppliers, Articles, Quotation Evaluations, Profit & Loss, Buyer Quotes, Buyer Orders, Supplier Quotes, Supplier Orders
  - **Import + Export** (master data): People, Buyers, Suppliers, Articles (column mapping, optional custom fields)
  - **Export only** (transactional/report data): Quotation Evaluations, Profit & Loss, Buyer Quotes, Buyer Orders, Supplier Quotes, Supplier Orders
  - Export completion notifications include download links (CSV/XLSX); custom `ExportCompletion` job refreshes the Export model from the DB so links appear correctly when using the queue
  - Implementation: `app/Filament/Exports/` (exporters), `app/Filament/Imports/` (importers), list page `getHeaderActions()` and table `ExportBulkAction` on each resource
- **Scheduled jobs** (`routes/console.php`): Quote expiration and alerts run daily:
  - **Expiring quotes** (08:00) – `CheckExpiringQuotesJob`: notifies quote creator when a buyer quote is expiring in 7, 3, or 1 day(s)
  - **Expired quotes** (08:30) – `CheckExpiredQuotesJob`: for quotes that expired the previous day, emails the buyer and key accounts (`QuoteExpiredMail`), and sends key accounts an in-app notification (`QuoteExpiredNotification`)

## Requirements

- PHP 8.4+
- PostgreSQL 15+
- Composer 2
- Node.js 20+
- Redis (optional, for queues)

## Installation

```bash
git clone <repository-url>
cd erpc
composer app-install
```

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
├── Actions/           # Single-purpose action classes
├── Data/              # Data transfer objects (Spatie Laravel Data)
├── Enums/             # PHP enums
├── Filament/          # Admin panel resources
│   ├── Exports/        # Exporters (e.g., BuyerExporter), Jobs/ExportCompletion (refresh before notification)
│   ├── Imports/        # Importers for master data (e.g., BuyerImporter)
│   ├── Pages/         # Custom Filament pages (e.g., EmailSettings)
│   ├── Resources/     # Filament resources (e.g., CreditLimitAcceptanceReportResource for Acceptance Report)
│   └── Widgets/       # Filament widgets (e.g., RequestInformationFlowWidget for Request View)
├── Jobs/              # Background jobs (e.g. Erp/CheckExpiredQuotesJob, CheckExpiringQuotesJob)
├── Mail/              # Laravel Mailables
│   └── Erp/           # ERP-specific email templates (e.g. QuoteExpiredMail, QuoteToBuyerMail)
├── Notifications/     # Laravel Notifications
│   └── Erp/           # ERP notifications (e.g. QuoteExpiredNotification, QuoteExpirationNotification)
├── Models/            # Eloquent models (e.g., EmailTemplate)
├── Observers/         # Model observers
├── Policies/          # Authorization policies (e.g., EmailTemplatePolicy)
├── Services/          # Service classes
│   └── Email/         # Email template and SMTP services (EmailTemplateService)
└── Support/           # Application support classes
    └── Media/         # DocumentPathGenerator (Spatie Media Library path per feature)

app-modules/           # Isolated modules
├── Documentation/
├── OnboardSeed/
└── SystemAdmin/

openspec/              # Specifications
└── specs/             # Feature specifications
```

## License

AGPL-3.0
