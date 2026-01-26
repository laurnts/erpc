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
- **Profit & Loss** - Generate PNL documents with items by supplier, cost/sell/margin analysis, and approval workflow
- **Order Processing** - Manage buyer and supplier purchase orders
- **Invoicing** - Handle buyer and supplier invoices with payment tracking
- **Shipment Tracking** - Monitor delivery status and logistics with Delivery Order (DO) PDF generation for inbound shipments

### Core Entities
- **Companies** - Buyers and suppliers with contacts
- **Articles** - Product catalog with flexible attributes
- **Projects** - Group related requests for large deals
- **Currencies & Exchange Rates** - Multi-currency support
- **Tax Codes** - Configurable tax handling per item
- **Key Accounts** - Personnel for approval workflows in QE and PNL documents

### CRM Capabilities
- **People/Contacts** - Contact management linked to companies
- **Opportunities** - Sales pipeline tracking
- **Tasks & Notes** - Activity management
- **AI Summaries** - AI-powered entity summaries

### Platform Features
- **Multi-Team** - Isolated workspaces per team
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
├── Enums/             # PHP enums
├── Filament/          # Admin panel resources
├── Jobs/              # Background jobs
├── Models/            # Eloquent models
├── Observers/         # Model observers
├── Policies/          # Authorization policies
└── Services/          # Service classes

app-modules/           # Isolated modules
├── Documentation/
├── OnboardSeed/
└── SystemAdmin/

openspec/              # Specifications
└── specs/             # Feature specifications
```

## License

AGPL-3.0
