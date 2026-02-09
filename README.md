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
- **Supplier Quoting** - Collect and compare quotes from multiple suppliers
- **Quotation Evaluation** - Generate internal QE documents with item comparison, supplier info, and approval workflow
- **Buyer Quoting** - Generate consolidated quotes with margin analysis
  - **Buyer PO Upload** - Upload and view buyer purchase order files via action button (available when quote status is Accepted)
- **Profit & Loss** - Generate PNL documents with items by supplier, cost/sell/margin analysis, and approval workflow
- **Order Processing** - Manage buyer and supplier purchase orders
  - **Supplier Order Approval** - Dual-approval workflow requiring minimum 2 approvals from senior roles (Dept Head of Sales, Deputy Director, Director) before supplier orders can be sent
- **Invoicing** - Handle buyer and supplier invoices with payment tracking
- **Shipment Tracking** - Monitor delivery status and logistics with Delivery Order (DO) PDF generation for inbound shipments

### Core Entities
- **Companies** - Buyers and suppliers with contacts
- **Articles** - Product catalog with flexible attributes
- **Projects** - Group related requests for large deals
- **Currencies & Exchange Rates** - Multi-currency support
- **Tax Codes** - Configurable tax handling per item
- **Team Members** - Central Purchasing personnel for approval workflows in QE, PNL documents, and Supplier Orders (managed as team members with Central Purchasing role)

### CRM Capabilities
- **People/Contacts** - Contact management linked to companies
- **Opportunities** - Sales pipeline tracking
- **Tasks & Notes** - Activity management
- **AI Summaries** - AI-powered entity summaries

### Platform Features
- **Multi-Team** - Isolated workspaces per team
- **Team Member Roles** - Three role types:
  - **Administrator** - Full access to all features
  - **Editor** - Read, create, and update permissions
  - **Central Purchasing** - Read, create, and update permissions with hierarchical sub-roles:
    - Key Account (prepares QE/PNL documents)
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
- **Import/Export** - CSV data management

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
│   ├── Pages/         # Custom Filament pages (e.g., EmailSettings)
│   └── Resources/     # Filament resources (e.g., EmailTemplateResource)
├── Jobs/              # Background jobs
├── Mail/              # Laravel Mailables
│   └── Erp/           # ERP-specific email templates
├── Models/            # Eloquent models (e.g., EmailTemplate)
├── Observers/         # Model observers
├── Policies/          # Authorization policies (e.g., EmailTemplatePolicy)
└── Services/          # Service classes
    └── Email/         # Email template and SMTP services (EmailTemplateService)

app-modules/           # Isolated modules
├── Documentation/
├── OnboardSeed/
└── SystemAdmin/

openspec/              # Specifications
└── specs/             # Feature specifications
```

## License

AGPL-3.0
