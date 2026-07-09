# ERP Trading System - Business Requirements Document

Request-Centric Trading Management System

*For Middleman/Broker Operations Without Inventory*

> **Platform:** Laravel 12 + Filament 4 + PostgreSQL (Built on Relaticle CRM)
> **Tables:** 30 new tables
> **Version history:** See version.md

---

## 1. Executive Summary

This document outlines the requirements for a self-hosted, open-source
system designed specifically for trading intermediaries operating
without physical inventory. The system is built as an extension to the
**Relaticle CRM** platform, leveraging its existing infrastructure for
multi-tenancy, custom fields, file attachments, and settings management.

**Foundation Platform: Relaticle CRM**
- Laravel 12 + Filament 4 + PostgreSQL
- Team-based multi-tenancy via Laravel Jetstream
- Custom fields system (relaticle/custom-fields package)
- File attachments via Spatie Media Library
- Configuration via Spatie Settings
- RBAC via Spatie Permission (to be installed)

The core terminology uses \'Request\' as the atomic unit of work, with
clear separation between Buyer-side and Supplier-side processes. Payment
terms are negotiated during the Buyer Quote stage and locked into the
Buyer Order upon acceptance.

Multiple Requests can be grouped under a \'Project\' for large deals
that involve several related buyer inquiries.

1.1 Key Changes in Version 3.0 (Relaticle Integration)

-   **Built on Relaticle CRM:** Leverages existing multi-tenancy, custom
    fields, file attachments, and settings infrastructure

-   **Reuse existing packages:** Spatie Media Library for attachments,
    Spatie Settings for configuration, Spatie Permission for RBAC

-   **Optional CRM linking:** Buyers and Suppliers can optionally link to
    existing CRM Companies via nullable company_id FK for contact sync

-   **Zero breaking changes:** Existing CRM functionality (Companies,
    People, Opportunities, Tasks, Notes) remains fully functional

1.2 Key Concepts from Version 2.0

-   **Request as atomic unit:** Renamed from "Project" to "Request";
    Projects now group multiple Requests for large deals

-   **Multi-supplier requests:** One request can now source from
    multiple suppliers (previously single supplier)

-   **Vague capture workflow:** Request items capture what buyer asked for
    (even if vague), then match to actual articles during sourcing stage

-   **Categories system:** Shared categories between articles and suppliers
    replace hierarchical categories (UI: "Categories", internal: tags table)

-   **Single base currency:** Sales in one currency (base), purchases in
    multiple currencies with auto-conversion and exchange rate screen

-   **Supplier confidentiality:** Buyer never sees supplier information;
    supplier details are internal only

-   **Quote extensions:** Extend quote validity with required reason
    logging instead of cancellation

-   **Manual journaling:** Payment and shipment status tracking with
    file upload proofs via Spatie Media Library (no third-party integrations)

-   **Local tax support:** Tax calculation on quotes, orders, and
    invoices (default 11%)

-   **Role-based access:** Full RBAC via Spatie Permission package

-   **ERP terminology:** UI shows friendly names with optional ERP term
    labels (PO, SO, DO, AR, etc.) for familiarity

2\. Business Context

2.1 Operating Model

-   **No Inventory Holdings:** Products are never stocked; all sourcing
    is on-demand

-   **Relay-Based Transactions:** Each transaction involves receiving
    buyer requests, obtaining supplier quotes, and relaying purchase
    orders in both directions

-   **Multi-Supplier Transactions:** Each project may involve multiple
    suppliers for different items

-   **High Customer Variability:** Many one-time buyers;
    customer-centric CRM features less valuable

-   **Unpredictable Product Mix:** Products and specifications vary
    dramatically; flat tags for classification

-   **Dynamic Pricing:** All prices negotiated per-project and locked at
    quote/order level

-   **Multi-Currency Operations:** Suppliers may quote in different
    currencies (IDR, USD, etc.)

-   **Manual Journaling:** No third-party integrations; pure data entry
    with proof uploads

2.2 The Request as the Atomic Unit

A Request represents the complete lifecycle of a single buyer inquiry
from initial request through final payment. Every document belongs to
exactly one Request, enabling complete visibility, instant profitability
calculation, and clear accountability per transaction.

Key relationships:

-   One Request belongs to one Buyer

-   One Request can have multiple Supplier Quotes (from different
    suppliers)

-   One Request has one consolidated Buyer Quote (combining items from
    multiple suppliers)

-   One Request has one Buyer Order but multiple Supplier Orders

-   One Request can have multiple Shipments (one per supplier)

-   One Request can have multiple Supplier Invoices but typically one
    Buyer Invoice flow

2.3 Projects as Request Groups

For large deals, multiple related Requests can be grouped under a single
Project. This enables:

-   Consolidated reporting across related transactions
-   Single point of reference for complex deals
-   Easier tracking of large customer engagements

A Project is optional - Requests can exist independently without being
assigned to a Project.

3\. Core Transaction Flow

3.1 Request Lifecycle Stages

  --------------- ----------------- ----------------------------------------
  **Stage**       **Description**   **Key Actions**

  **1. New**      Request received  Log buyer details, capture request items
                                    (may be vague, e.g., "tyre for Prius")

  **2. Sourcing** Clarify & match   Research articles, contact suppliers,
                                    match request items to actual articles

  **3. Supplier   Collect quotes    Receive quotes from multiple suppliers
  Quoting**                         (requires all items matched to articles)

  **4. Buyer      Prepare quote     Consolidate supplier items, set margins,
  Quoting**                         add tax, define payment terms

  **5.            Handle changes    Revise quotes, extend validity with
  Negotiation**                     reason, adjust terms as needed

  **6. Buyer PO** Order confirmed   Create buyer order, lock payment terms,
                                    perform credit check

  **7. Supplier   Place orders      Generate multiple supplier POs (one per
  PO**                              supplier)

  **8.            Track delivery    Track multiple shipments, upload PODs,
  Fulfillment**                     update statuses manually

  **9.            Bill & pay        Issue buyer invoice, record supplier
  Invoicing**                       invoices, track payments with proofs

  **10. Closed**  Complete          All payments settled, calculate final
                                    P&L in base currency
  --------------- ----------------- ----------------------------------------

3.2 Multi-Supplier Request Flow

A single request can source items from multiple suppliers. The flow
consolidates on the buyer side:

  ----------------------------------- -----------------------------------
  **Supplier Side (Multiple)**        **Buyer Side (Consolidated)**

  Supplier Quote #1 (Supplier A, IDR) 

  Supplier Quote #2 (Supplier B, USD) 1 Buyer Quote (consolidated in
                                      buyer currency)

  Supplier Quote #3 (Supplier C, IDR) 

  3 Supplier Orders (one per          1 Buyer Order (consolidated)
  supplier)                           

  3 Supplier Invoices                 1-2 Buyer Invoices (prepay +
                                      balance)

  3 Inbound Shipments                 1 Outbound Shipment (optional)
  ----------------------------------- -----------------------------------

4\. Categories System

Version 2.0 replaces hierarchical product categories with a flat, shared
categories system for maximum flexibility. (Internal table name: `tags`)

4.1 Category Characteristics

-   **Flat structure:** No parent-child hierarchy; all categories are equal

-   **Shared pool:** Same categories used for both articles and suppliers

-   **Duplicate prevention:** Autocomplete suggests existing categories;
    prevents duplicates

-   **Color-coded:** Optional hex color for visual distinction in UI
    badges

4.2 Category Usage

-   **On Articles:** Describes what the article is (e.g., Chemicals,
    Industrial, Hazmat)

-   **On Suppliers:** Describes what they supply (e.g., Chemicals,
    Electronics, ISO-Certified)

-   **For Sourcing:** \"Find suppliers who can provide \[Chemicals\] +
    \[Industrial\]\" uses same categories

4.3 Example Categories

Chemicals, Electronics, Industrial, Hazmat, Medical, Food-Grade,
ISO-Certified, Motors, Controls, Metal, Fabrication, Solvents,
Components, Safety

5\. Multi-Currency Support

5.1 Currency Model

-   **Single base currency:** All sales (buyer quotes, orders, invoices)
    are in ONE base currency (e.g., USD). This is the currency you get paid in.

-   **Supplier multi-currency:** Suppliers may quote in different currencies
    (IDR, USD, EUR, etc.). These are auto-converted to base currency.

-   **Exchange rate snapshot:** Exchange rate captured and stored at
    time of each supplier transaction for audit trail

-   **Simplified display:** Buyer-facing documents only show base currency
    amounts. Supplier costs show original currency + converted amount.

5.2 Exchange Rate Management

-   **Exchange Rate Screen:** Admin manages rates manually via dedicated screen

-   Rates are entered manually by admin (no third-party API integration)

-   Historical rates stored per date for complete audit trail

-   Each supplier quote, order, and invoice stores the rate used at that moment

-   Converted amounts (total_in_base) calculated and stored for reporting

5.3 Supported Currencies

  ----------- -------------------------- ------------ ---------------------
  **Code**    **Name**                   **Symbol**   **Decimals**

  USD         US Dollar                  \$           2

  IDR         Indonesian Rupiah          Rp           0

  EUR         Euro                       €            2

  SGD         Singapore Dollar           S\$          2

  CNY         Chinese Yuan               ¥            2
  ----------- -------------------------- ------------ ---------------------

6\. Quote Validity & Extensions

Quotes are not cancelled when they expire. Instead, validity can be
extended with proper documentation for audit purposes.

6.1 Extension Requirements

-   **New valid until date:** Required - the new expiration date

-   **Reason for extension:** Required - text explanation for why
    extension is needed

-   **Prices changed flag:** Yes/No indicator if supplier costs have
    changed

-   **Availability changed flag:** Yes/No indicator if item availability
    has changed

-   **Change notes:** Optional details about what specifically changed

-   **Extended by (user):** Automatically recorded for accountability

6.2 Extension Tracking

All extensions are logged in a dedicated buyer_quote_extensions table.
Each extension record includes:

-   Previous valid_until date

-   New valid_until date

-   Required reason text

-   Boolean flags for price and availability changes

-   Optional change notes

-   Timestamp and user who extended

Extensions appear in the project activity log for complete audit trail.

7\. Payment Terms and Credit Management

7.1 Payment Terms Flow

Payment terms follow a specific lifecycle:

1.  During Buyer Quote creation, admin sets proposed payment terms
    (prepayment %, net days, notes)

2.  Buyer may negotiate; quote revisions capture updated terms

3.  When Buyer PO is received, the accepted quote\'s terms are copied to
    the Buyer Order

4.  The Buyer Order becomes the contractual record with LOCKED terms

5.  Invoices and payments reference the Buyer Order terms

7.2 Credit Limit System

-   **Credit Limit:** Maximum outstanding balance per buyer (stored on
    Buyer record)

-   **Available Credit:** Credit Limit minus unpaid Buyer Invoices
    (computed in real-time)

-   **Credit Check:** System warns when creating orders if total exceeds
    available credit

-   **Credit Hold:** Boolean flag to block new orders on credit terms

8\. Manual Journaling

All payment and shipment tracking is done through manual data entry with
file upload proofs. No third-party integrations are required.

8.1 Payment Recording

Each payment record includes:

-   Amount and currency (can differ from invoice currency with exchange
    rate)

-   Payment date

-   Method (bank_transfer, cash, check, lc, other)

-   Reference number (bank ref, check number, etc.)

-   Notes field for journal entry

-   **File upload:** Required: Proof of payment attachment (bank
    statement, transfer confirmation)

-   **Recorded by:** Who logged this payment

8.2 Shipment Tracking (Manual Journaling)

**Manual journaling approach.** Each supplier uses their own shipper - we
record what they tell us, not manage logistics. Priority: status updates,
tracking reference, carrier info.

Each shipment record includes:

-   Type: inbound (from supplier) or outbound (to buyer)

-   **Status (PRIMARY FIELD):** pending, in_transit, delivered, partial, failed

-   Carrier name (supplier's shipper: JNE, JNT, SiCepat, etc.)

-   Tracking number (for reference/lookup, not live integration)

-   Shipped date, expected delivery, actual delivery

-   Notes field for journal entries

-   Recorded by (user who logged the shipment)

-   **Document uploads:** Bill of lading, packing list, signed POD

**Workflow:**
1. Supplier ships goods, sends tracking info (WA/email)
2. Admin creates shipment record with carrier + tracking number
3. Admin updates status as supplier/shipper provides updates
4. On receipt, admin records what was actually received

8.3 Attachment Types

  ------------------ ----------------------------------------------------
  **Type**           **Description**

  payment_proof      Bank statement, transfer confirmation, receipt

  shipping_doc       Bill of lading, packing list, airway bill

  pod                Proof of delivery (signed delivery receipt)

  invoice_copy       Scanned invoice document

  quote_doc          Supplier quote document/PDF

  po_copy            Purchase order copy

  other              Miscellaneous documents
  ------------------ ----------------------------------------------------

9\. Tax Handling

Tax is applied at the **line item level** following ERP best practices.

**9.1 Item-Level Tax (v3.2)**

Each line item (quote items, order items, invoice items) has:
-   **tax_code_id:** FK to tax_codes table (e.g., PPN 11%, Zero Rate, Exempt)
-   **is_tax_inclusive:** Boolean - whether entered price includes tax
-   **tax_rate:** Snapshotted rate at time of save (doesn't change if tax_code rate changes)
-   **unit_price_exc_tax:** Calculated price excluding tax

**9.2 Tax Calculation Service**
-   If is_tax_inclusive = true: unit_price_exc_tax = unit_price / (1 + tax_rate)
-   If is_tax_inclusive = false: unit_price_exc_tax = unit_price
-   tax_amount = subtotal × tax_rate
-   total = subtotal + tax_amount

**9.3 Header-Level Tax Default**

Quotes and orders have `default_tax_code_id` for convenience:
-   When adding items, tax_code defaults from header
-   Each item can override the default
-   Default hierarchy: article default → document default → team default

**9.4 Tax Codes Table**

  --------------- --------------- ----------------------------------------
  **Code**        **Rate**        **Description**

  PPN 11%         11%             Standard Indonesian VAT
  Zero Rate       0%              Zero-rated goods
  Exempt          0%              Tax exempt
  --------------- --------------- ----------------------------------------

10\. Database Schema Design

10.1 Design Principles

-   **Separate Buyers and Suppliers:** Distinct tables for buyers and
    suppliers with different attributes

-   **Tags Over Categories:** Flat tags replace hierarchical categories
    for flexibility

-   **Line Items as Tables:** Quote/order line items are normalized
    tables (not JSONB arrays)

-   **Payment Terms on Orders:** Negotiated on quotes, copied and locked
    to orders

-   **Soft Deletes:** All business entities use SoftDeletes for audit
    trail

-   **Multi-Currency:** Exchange rates stored per transaction

-   **File Attachments:** Polymorphic attachments for any entity

10.2 Entity Relationship Overview

Tags ─\<─ Taggables (polymorphic) ─\>─ Articles, Suppliers

Project ──\< Request (optional grouping)

Buyer ──\< Request ──\< SupplierQuote (multiple) \>── Supplier

Request ──\< BuyerQuote ──\< BuyerQuoteItem

Request ─── BuyerOrder ──\< BuyerOrderItem

Request ──\< SupplierOrder (multiple) ──\< SupplierOrderItem

Request ──\< BuyerInvoice ──\< BuyerPayment

Request ──\< SupplierInvoice (multiple) ──\< SupplierPayment

Request ──\< Shipment (multiple)

\* ──\< Attachment (polymorphic)

10.3 Categories Tables (Internal: tags)

10.3.1 tags

Flat shared categories for articles and suppliers. UI shows "Categories".

  --------------- --------------- ----------------------------------------
  **Column**      **Type**        **Notes**

  id              bigint PK       Auto-increment primary key

  name            varchar(100)    Tag name (unique)

  slug            varchar(100)    URL-friendly slug (unique)

  color           varchar(7)      Hex color for UI badge (nullable)

  timestamps      \-\--           created_at, updated_at
  --------------- --------------- ----------------------------------------

10.3.2 taggables (Polymorphic Pivot)

Links tags to articles and suppliers.

  --------------- --------------- ----------------------------------------
  **Column**      **Type**        **Notes**

  id              bigint PK       Auto-increment primary key

  tag_id          bigint FK       Links to tags

  taggable_type   varchar(255)    Model class (Article, Supplier)

  taggable_id     bigint          ID of the tagged entity
  --------------- --------------- ----------------------------------------

10.4 Currency Tables

10.4.1 currencies

  ---------------- --------------- ---------------------------------------
  **Column**       **Type**        **Notes**

  id               bigint PK       Auto-increment primary key

  code             char(3)         ISO code (USD, IDR) - unique

  name             varchar(100)    Full name (US Dollar)

  symbol           varchar(10)     Display symbol (\$, Rp)

  decimal_places   integer         Number of decimals (2 for USD, 0 for
                                   IDR)

  is_active        boolean         Whether currency is available for use
  ---------------- --------------- ---------------------------------------

10.4.2 exchange_rates

Historical exchange rates for conversion tracking.

  ----------------- ---------------- ---------------------------------------
  **Column**        **Type**         **Notes**

  id                bigint PK        Auto-increment primary key

  base_currency     char(3)          From currency (e.g., IDR)

  target_currency   char(3)          To currency (e.g., USD)

  rate              decimal(20,10)   Exchange rate value

  effective_date    date             Date this rate applies

  source            varchar(100)     Where rate came from (manual, bank)

  recorded_by       bigint FK        User who entered the rate
  ----------------- ---------------- ---------------------------------------

*Unique constraint on (base_currency, target_currency, effective_date).*

10.5 Core Master Data Tables

10.5.1 buyers

Companies that purchase products through your brokerage. Can optionally
link to existing CRM Companies for contact synchronization.

  ------------------ --------------- ---------------------------------------
  **Column**         **Type**        **Notes**

  id                 bigint PK       Auto-increment primary key

  team_id            bigint FK       Team-based multi-tenancy (Relaticle)

  company_id         bigint FK       **OPTIONAL** link to CRM Company
                                     (nullable, nullOnDelete)

  code               varchar(20)     Unique code (BUY-001), auto-generated

  name               varchar(255)    Company name

  contact_name       varchar(255)    Primary contact person

  email              varchar(255)    Primary email

  phone              varchar(50)     Phone number

  address            text            Full address (nullable)

  credit_limit       decimal(15,2)   Max credit allowed; NULL = no limit

  is_on_hold         boolean         Block new credit orders; default false

  default_currency   char(3)         Preferred currency (default USD)

  notes              text            Internal notes

  creator_id         bigint FK       User who created (Relaticle trait)

  timestamps         \-\--           created_at, updated_at, deleted_at
  ------------------ --------------- ---------------------------------------

*Traits: HasTeam, HasCreator, UsesCustomFields*
*Relation: $buyer->company() optional BelongsTo for CRM contact sync*

10.5.2 suppliers

Companies from which products are sourced. Can optionally link to existing
CRM Companies for contact synchronization.

  ------------------------ --------------- ------------------------------------
  **Column**               **Type**        **Notes**

  id                       bigint PK       Auto-increment primary key

  team_id                  bigint FK       Team-based multi-tenancy (Relaticle)

  company_id               bigint FK       **OPTIONAL** link to CRM Company
                                           (nullable, nullOnDelete)

  code                     varchar(20)     Unique code (SUP-001)

  name                     varchar(255)    Company name

  contact_name             varchar(255)    Primary contact person

  email                    varchar(255)    Primary email

  phone                    varchar(50)     Phone number

  address                  text            Full address (nullable)

  default_payment_terms    varchar(100)    Default terms (Net 30)

  default_lead_time_days   integer         Typical lead time

  default_currency         char(3)         Preferred currency

  notes                    text            Internal notes

  creator_id               bigint FK       User who created (Relaticle trait)

  timestamps               \-\--           created_at, updated_at, deleted_at
  ------------------------ --------------- ------------------------------------

*Traits: HasTeam, HasCreator, HasTags, UsesCustomFields*
*Relation: $supplier->company() optional BelongsTo for CRM contact sync*
*Relation: $supplier->tags() via taggables polymorphic pivot*

10.5.3 articles

Articles sourced for buyers. Can be products, services, or any sellable item.
Attributes vary, stored as JSONB.

  --------------- --------------- ----------------------------------------
  **Column**      **Type**        **Notes**

  id              bigint PK       Auto-increment primary key

  name            varchar(255)    Article name/title

  sku             varchar(50)     Optional internal SKU (unique, nullable)

  description     text            Brief description (nullable)

  unit            varchar(50)     Unit of measure (pcs, kg, ltr, box)

  attributes      jsonb           Flexible key-value specs

  timestamps      \-\--           created_at, updated_at, deleted_at
  --------------- --------------- ----------------------------------------

*Relation: \$article-\>tags() via taggables polymorphic pivot.*

JSONB attributes examples:

*{\"material\": \"stainless steel\", \"grade\": \"304\",
\"thickness_mm\": 2.5}*

*{\"voltage\": \"220V\", \"wattage\": 1500, \"certification\": \"CE\"}*

*{\"purity\": \"99.5%\", \"cas_number\": \"67-64-1\"}*

10.5.4 supplier_articles (Pivot)

Links suppliers to articles they can supply; tracks historical pricing.

  ---------------------- --------------- ------------------------------------
  **Column**             **Type**        **Notes**

  id                     bigint PK       Auto-increment primary key

  supplier_id            bigint FK       Links to suppliers

  article_id             bigint FK       Links to articles

  last_quoted_price      decimal(15,2)   Most recent price (for reference)

  last_quoted_currency   char(3)         Currency of last quote

  last_quoted_at         date            Date of most recent quote

  notes                  text            Supplier-specific notes
  ---------------------- --------------- ------------------------------------

*Unique constraint on (supplier_id, article_id). Auto-updated when
quotes received.*

10.6 Requests

10.6.1 requests

The atomic unit representing a single buyer inquiry from initial request
through final payment.

NOTE: Multiple Requests can optionally be grouped under a Project for
large deals. See section 10.6.3 for the projects table.

  ---------------- --------------- ---------------------------------------
  **Column**       **Type**        **Notes**

  id               bigint PK       Auto-increment primary key

  request_number   varchar(20)     Human-readable ID (REQ-2024-0001)

  project_id       bigint FK       Optional link to projects (for grouping)

  buyer_id         bigint FK       Links to buyers

  title            varchar(255)    Brief title summarizing the request

  stage            varchar(50)     Current lifecycle stage

  requirements     text            Buyer\'s original requirements

  base_currency    char(3)         Single currency for all sales/reporting

  closed_at        timestamp       When request closed (nullable)

  timestamps       \-\--           created_at, updated_at, deleted_at
  ---------------- --------------- ---------------------------------------

NOTE: Removed buyer_currency. All buyer-facing documents use base_currency.
Suppliers quote in their own currencies, auto-converted to base_currency.

10.6.2 request_items

Captures what the buyer asked for, even if vague. Articles are linked
later when clarified during sourcing.

  --------------- --------------- ----------------------------------------
  **Column**      **Type**        **Notes**

  id              bigint PK       Auto-increment primary key

  request_id      bigint FK       Links to requests

  description     varchar(500)    What buyer asked for (required)
                                  e.g., "Tyre for Toyota Prius 2020"

  quantity        decimal(15,3)   Requested quantity

  unit            varchar(50)     Unit of measure (pcs, kg, set, etc.)

  article_id      bigint FK       Links to articles (NULLABLE - linked
                                  when clarified)

  matched_at      timestamp       When article was linked (nullable)

  matched_by      bigint FK       Who performed the match (nullable)

  notes           text            Additional notes

  timestamps      \-\--           created_at, updated_at, deleted_at
  --------------- --------------- ----------------------------------------

*Soft deletes enabled to preserve history if items are removed.*

**Workflow:**
1. NEW stage: Create items with description only (article_id = NULL)
2. SOURCING stage: Research, clarify, match articles
3. SUPPLIER_QUOTING: All items must have article_id matched

**Validation:** Cannot move to SUPPLIER_QUOTING until all items matched.

10.6.3 projects

Groups multiple related Requests for large deals. Optional container.

  ---------------- --------------- ---------------------------------------
  **Column**       **Type**        **Notes**

  id               bigint PK       Auto-increment primary key

  project_number   varchar(20)     Human-readable ID (PRJ-2024-0001)

  buyer_id         bigint FK       Links to buyers (same buyer for all requests)

  name             varchar(255)    Project name/title

  description      text            Project description (nullable)

  status           varchar(50)     active, completed, on_hold, cancelled

  timestamps       \-\--           created_at, updated_at, deleted_at
  ---------------- ---------------

*Stage values (for requests): \'new\', \'sourcing\', \'supplier_quoting\',
\'buyer_quoting\', \'negotiation\', \'buyer_po_received\',
\'supplier_po_issued\', \'fulfillment\', \'invoicing\', \'closed\',
\'cancelled\'*

10.7 Quote Tables

10.7.1 supplier_quotes

Quotes received from suppliers for a request. Multiple per request allowed.

  ----------------------- ---------------- ------------------------------------
  **Column**              **Type**         **Notes**

  id                      bigint PK        Auto-increment primary key

  request_id              bigint FK        Links to requests

  supplier_id             bigint FK        Links to suppliers

  quote_number            varchar(50)      Supplier\'s reference (nullable)

  status                  varchar(30)      pending, selected, rejected, expired

  default_tax_code_id     bigint FK        Default tax for new items (nullable)

  subtotal                decimal(15,2)    Sum of line items

  tax_amount              decimal(15,2)    Tax amount

  total                   decimal(15,2)    Final total

  currency                char(3)          Supplier\'s currency (IDR, USD)

  exchange_rate_to_base   decimal(20,10)   Rate at time of quote

  total_in_base           decimal(15,2)    Converted to project base currency

  lead_time_days          integer          Promised delivery lead time

  valid_until             date             Quote expiration date

  notes                   text             Additional notes

  received_at             date             Date quote received

  timestamps              \-\--            created_at, updated_at, deleted_at
  ----------------------- ---------------- ------------------------------------

10.7.2 supplier_quote_items

  ------------------- --------------- ---------------------------------------
  **Column**          **Type**        **Notes**

  id                  bigint PK       Auto-increment primary key

  supplier_quote_id   bigint FK       Links to supplier_quotes

  request_item_id     bigint FK       Traceability to original request
                                      (nullable)

  article_id          bigint FK       Links to articles (nullable if ad-hoc)

  sort_order          integer         Display order (default 0)

  description         varchar(500)    Item description

  quantity            decimal(15,3)   Quoted quantity

  unit                varchar(50)     Unit of measure

  tax_code_id         bigint FK       Links to tax_codes (nullable)

  is_tax_inclusive    boolean         Price includes tax? (default false)

  tax_rate            decimal(5,2)    Snapshotted rate (default 0)

  unit_price          decimal(15,4)   Price per unit (as entered)

  unit_price_exc_tax  decimal(15,4)   Calculated price excluding tax

  subtotal            decimal(15,2)   quantity × unit_price_exc_tax

  tax_amount          decimal(15,2)   subtotal × tax_rate

  total               decimal(15,2)   subtotal + tax_amount

  notes               text            Item-specific notes
  ------------------- --------------- ---------------------------------------

*Traceability: Links supplier quote item back to the original buyer request
item.*

10.7.3 buyer_quotes

Quotes sent to buyers; supports versioning for negotiation. Consolidated
from multiple suppliers.

  ---------------------- --------------- ------------------------------------
  **Column**             **Type**        **Notes**

  id                     bigint PK       Auto-increment primary key

  request_id             bigint FK       Links to requests

  version                integer         Version number (1, 2, 3\...)

  parent_id              bigint FK       Self-ref to previous version

  quote_number           varchar(50)     Generated ref (Q-2024-0001-v2)

  status                 varchar(30)     draft, sent, accepted, rejected,
                                         expired, superseded

  default_tax_code_id    bigint FK       Default tax for new items (nullable)

  subtotal               decimal(15,2)   Sum of line items

  tax_amount             decimal(15,2)   Sum of item tax amounts

  total                  decimal(15,2)   Final total to buyer

  currency               char(3)         Buyer\'s currency

  valid_until            date            Quote expiration date

  original_valid_until   date            Track if extended

  prepayment_percent     decimal(5,2)    Required prepayment 0-100

  net_days               integer         Net payment days (30)

  payment_terms_notes    text            Additional payment conditions

  notes                  text            General notes

  sent_at                timestamp       When sent to buyer

  accepted_at            timestamp       When buyer accepted

  timestamps             \-\--           created_at, updated_at, deleted_at
  ---------------------- --------------- ------------------------------------

10.7.4 buyer_quote_items

Line items on buyer quotes; consolidated from multiple suppliers with
margin calculation. All values EDITABLE during negotiation; locked when
order is created.

  ------------------------ --------------- ------------------------------------
  **Column**               **Type**        **Notes**

  id                       bigint PK       Auto-increment primary key

  buyer_quote_id           bigint FK       Links to buyer_quotes

  request_item_id          bigint FK       Traceability to original request
                                           (nullable)

  article_id               bigint FK       Links to articles (nullable)

  supplier_quote_item_id   bigint FK       Link to source supplier item

  sort_order               integer         Display order (default 0)

  description              varchar(500)    Item description shown to buyer

  quantity                 decimal(15,3)   Quoted quantity

  unit                     varchar(50)     Unit of measure

  cost_price               decimal(15,4)   Our cost (from supplier) - EDITABLE

  cost_currency            char(3)         Supplier\'s currency

  tax_code_id              bigint FK       Links to tax_codes (nullable)

  is_tax_inclusive         boolean         Price includes tax? (default false)

  tax_rate                 decimal(5,2)    Snapshotted rate (default 0)

  unit_price               decimal(15,4)   Price to buyer (as entered) - EDITABLE

  unit_price_exc_tax       decimal(15,4)   Calculated price excluding tax

  subtotal                 decimal(15,2)   quantity × unit_price_exc_tax

  tax_amount               decimal(15,2)   subtotal × tax_rate

  total                    decimal(15,2)   subtotal + tax_amount

  margin_percent           decimal(5,2)    Calculated margin %

  notes                    text            Item-specific notes
  ------------------------ --------------- ------------------------------------

*Traceability: Full chain from request_item → supplier_quote_item →
buyer_quote_item.*

10.7.5 buyer_quote_extensions

Track quote validity extensions with required reasons.

  ---------------------- --------------- ------------------------------------
  **Column**             **Type**        **Notes**

  id                     bigint PK       Auto-increment primary key

  buyer_quote_id         bigint FK       Links to buyer_quotes

  previous_valid_until   date            Old expiration date

  new_valid_until        date            New expiration date

  reason                 text            Required reason for extension

  prices_changed         boolean         Have prices changed?

  availability_changed   boolean         Has availability changed?

  change_notes           text            Details of what changed

  extended_by            bigint FK       User who extended

  timestamps             \-\--           created_at, updated_at
  ---------------------- --------------- ------------------------------------

10.8 Order Tables

10.8.1 buyer_orders

Purchase orders from buyers. Created when buyer accepts quote. Payment
terms LOCKED here.

  --------------------- --------------- ------------------------------------
  **Column**            **Type**        **Notes**

  id                    bigint PK       Auto-increment primary key

  request_id            bigint FK       Links to requests (unique: 1 per
                                        project)

  buyer_quote_id        bigint FK       Links to accepted buyer_quote

  po_number             varchar(100)    Buyer\'s PO reference

  order_number          varchar(50)     Our internal number (BO-2024-0001)

  status                varchar(30)     confirmed, in_progress, fulfilled,
                                        cancelled

  default_tax_code_id   bigint FK       Default tax (copied from quote)

  subtotal              decimal(15,2)   Sum of line items

  tax_amount            decimal(15,2)   Sum of item tax amounts

  total                 decimal(15,2)   Final order total

  currency              char(3)         Currency

  prepayment_percent    decimal(5,2)    LOCKED from accepted quote

  net_days              integer         LOCKED from accepted quote

  payment_terms_notes   text            LOCKED from accepted quote

  expected_delivery     date            Expected delivery date

  received_at           date            Date buyer PO received

  timestamps            \-\--           created_at, updated_at, deleted_at
  --------------------- --------------- ------------------------------------

*Payment terms are explicitly copied from accepted quote to lock them
contractually.*

10.8.2 buyer_order_items

  -------------------- --------------- ----------------------------------------
  **Column**           **Type**        **Notes**

  id                   bigint PK       Auto-increment primary key

  buyer_order_id       bigint FK       Links to buyer_orders

  buyer_quote_item_id  bigint FK       Traceability to quote item (nullable)

  article_id           bigint FK       Links to articles (nullable)

  supplier_id          bigint FK       Which supplier fulfills this item

  sort_order           integer         Display order (default 0)

  description          varchar(500)    Item description

  quantity             decimal(15,3)   Ordered quantity

  unit                 varchar(50)     Unit of measure

  tax_code_id          bigint FK       Links to tax_codes (LOCKED)

  is_tax_inclusive     boolean         Price includes tax? (LOCKED)

  tax_rate             decimal(5,2)    Snapshotted rate (LOCKED)

  unit_price           decimal(15,4)   Locked unit price (as entered)

  unit_price_exc_tax   decimal(15,4)   Calculated price excluding tax

  subtotal             decimal(15,2)   quantity × unit_price_exc_tax

  tax_amount           decimal(15,2)   subtotal × tax_rate

  total                decimal(15,2)   subtotal + tax_amount
  -------------------- --------------- ----------------------------------------

10.8.3 supplier_orders

Purchase orders sent to suppliers. Multiple per request (one per
supplier).

  ----------------------- ---------------- ------------------------------------
  **Column**              **Type**         **Notes**

  id                      bigint PK        Auto-increment primary key

  request_id              bigint FK        Links to requests

  supplier_id             bigint FK        Links to suppliers

  supplier_quote_id       bigint FK        Links to selected supplier_quote

  po_number               varchar(50)      Our PO number (PO-2024-0001)

  status                  varchar(30)      draft, sent, confirmed, shipped,
                                           delivered, cancelled

  default_tax_code_id     bigint FK        Default tax (copied from quote)

  subtotal                decimal(15,2)    Sum of line items

  tax_amount              decimal(15,2)    Sum of item tax amounts

  total                   decimal(15,2)    Final total to supplier

  currency                char(3)          Supplier\'s currency

  exchange_rate_to_base   decimal(20,10)   Rate at time of order

  total_in_base           decimal(15,2)    Converted to base currency

  payment_terms           varchar(100)     Agreed terms with supplier

  expected_delivery       date             Expected delivery date

  actual_delivery         date             Actual delivery (nullable)

  sent_at                 date             Date PO sent

  timestamps              \-\--            created_at, updated_at, deleted_at
  ----------------------- ---------------- ------------------------------------

10.8.4 supplier_order_items

  ------------------------- --------------- ---------------------------------------
  **Column**                **Type**        **Notes**

  id                        bigint PK       Auto-increment primary key

  supplier_order_id         bigint FK       Links to supplier_orders

  supplier_quote_item_id    bigint FK       Traceability to quote item (nullable)

  article_id                bigint FK       Links to articles (nullable)

  sort_order                integer         Display order (default 0)

  description               varchar(500)    Item description

  quantity                  decimal(15,3)   Ordered quantity

  unit                      varchar(50)     Unit of measure

  tax_code_id               bigint FK       Links to tax_codes (LOCKED)

  is_tax_inclusive          boolean         Price includes tax? (LOCKED)

  tax_rate                  decimal(5,2)    Snapshotted rate (LOCKED)

  unit_price                decimal(15,4)   Locked unit price (as entered)

  unit_price_exc_tax        decimal(15,4)   Calculated price excluding tax

  subtotal                  decimal(15,2)   quantity × unit_price_exc_tax

  tax_amount                decimal(15,2)   subtotal × tax_rate

  total                     decimal(15,2)   subtotal + tax_amount
  ------------------------- --------------- ---------------------------------------

10.9 Invoice and Payment Tables

10.9.1 buyer_invoices

  -------------------- --------------- ----------------------------------------
  **Column**           **Type**        **Notes**

  id                   bigint PK       Auto-increment primary key

  request_id           bigint FK       Links to requests

  buyer_order_id       bigint FK       Links to buyer_orders

  original_invoice_id  bigint FK       For credit/debit notes: links to
                                       original invoice (nullable)

  invoice_number       varchar(50)     Invoice ref (INV-2024-0001)

  type                 varchar(30)     prepayment, balance, standard,
                                       credit_note, debit_note

  credit_reason        text            Required for credit notes (nullable)

  status               varchar(30)     draft, sent, partial, paid, overdue,
                                       cancelled

  subtotal             decimal(15,2)   Sum of item subtotals (negative for
                                       credit notes)

  tax_amount           decimal(15,2)   Sum of item tax amounts

  amount               decimal(15,2)   Total invoice amount (subtotal + tax)

  currency             char(3)         Currency

  issued_at            date            Date issued

  due_at               date            Payment due date

  paid_at              date            Date fully paid (nullable)

  timestamps           \-\--           created_at, updated_at, deleted_at
  -------------------- --------------- ----------------------------------------

*due_at calculated: if prepayment=100% then due_at=issued_at; else
due_at=delivery_date+net_days*

*Credit notes have negative amounts and reduce buyer's outstanding balance.*

10.9.1.1 buyer_invoice_items

Line items on buyer invoices, mirroring quote/order structure for traceability.

  ---------------------- --------------- ----------------------------------------
  **Column**             **Type**        **Notes**

  id                     bigint PK       Auto-increment primary key

  buyer_invoice_id       bigint FK       Links to buyer_invoices

  buyer_order_item_id    bigint FK       Traceability to order item (nullable)

  article_id             bigint FK       Links to articles (nullable)

  sort_order             integer         Display order (default 0)

  description            varchar(500)    Item description

  quantity               decimal(15,3)   Quantity (negative for credit notes)

  unit                   varchar(50)     Unit of measure

  tax_code_id            bigint FK       Links to tax_codes (nullable)

  is_tax_inclusive       boolean         Price includes tax? (default false)

  tax_rate               decimal(5,2)    Snapshotted rate (default 0)

  unit_price             decimal(15,4)   Unit price (as entered)

  unit_price_exc_tax     decimal(15,4)   Calculated price excluding tax

  subtotal               decimal(15,2)   quantity × unit_price_exc_tax

  tax_amount             decimal(15,2)   subtotal × tax_rate

  total                  decimal(15,2)   subtotal + tax_amount

  notes                  text            Item-specific notes (nullable)

  timestamps             \-\--           created_at, updated_at
  ---------------------- --------------- ----------------------------------------

*Traceability: buyer_order_item_id links invoice item back to order item for
audit trail.*

10.9.2 buyer_payments

Payment records for buyer invoices. Supports partial payments with proof
uploads.

  ------------------- ---------------- ---------------------------------------
  **Column**          **Type**         **Notes**

  id                  bigint PK        Auto-increment primary key

  buyer_invoice_id    bigint FK        Links to buyer_invoices

  amount              decimal(15,2)    Payment amount received

  currency            char(3)          Currency of payment

  original_amount     decimal(15,2)    If paid in different currency
                                       (nullable)

  original_currency   char(3)          Original payment currency

  exchange_rate       decimal(20,10)   Exchange rate used

  paid_at             date             Date payment received

  method              varchar(50)      bank_transfer, cash, check, lc, other

  reference           varchar(100)     Payment reference

  notes               text             Journal entry notes

  recorded_by         bigint FK        User who recorded payment

  timestamps          \-\--            created_at, updated_at
  ------------------- ---------------- ---------------------------------------

*Attachments (payment proofs) linked via polymorphic attachments table.*

10.9.3 supplier_invoices

Invoices received from suppliers. Multiple per request.

  ----------------------- ---------------- ------------------------------------
  **Column**              **Type**         **Notes**

  id                      bigint PK        Auto-increment primary key

  request_id              bigint FK        Links to requests

  supplier_order_id       bigint FK        Links to supplier_orders

  supplier_id             bigint FK        Links to suppliers

  original_invoice_id     bigint FK        For credit notes: links to original
                                           invoice (nullable)

  invoice_number          varchar(100)     Supplier\'s invoice reference

  type                    varchar(30)      standard, credit_note

  credit_reason           text             Reason for credit note (nullable)

  status                  varchar(30)      received, approved, paid, disputed

  subtotal                decimal(15,2)    Sum of item subtotals (negative for
                                           credit notes)

  tax_amount              decimal(15,2)    Sum of item tax amounts

  amount                  decimal(15,2)    Total invoice amount (subtotal + tax)

  currency                char(3)          Supplier\'s currency

  exchange_rate_to_base   decimal(20,10)   Rate snapshot

  amount_in_base          decimal(15,2)    Converted to base currency

  received_at             date             Date received

  due_at                  date             Payment due date

  paid_at                 date             Date we paid (nullable)

  timestamps              \-\--            created_at, updated_at, deleted_at
  ----------------------- ---------------- ------------------------------------

*Credit notes have negative amounts and reduce supplier cost calculations.*

10.9.3.1 supplier_invoice_items

Line items on supplier invoices for verification against orders.

  -------------------------- --------------- ----------------------------------------
  **Column**                 **Type**        **Notes**

  id                         bigint PK       Auto-increment primary key

  supplier_invoice_id        bigint FK       Links to supplier_invoices

  supplier_order_item_id     bigint FK       Traceability to order item (nullable)

  article_id                 bigint FK       Links to articles (nullable)

  sort_order                 integer         Display order (default 0)

  description                varchar(500)    Item description

  quantity                   decimal(15,3)   Quantity (negative for credit notes)

  unit                       varchar(50)     Unit of measure

  tax_code_id                bigint FK       Links to tax_codes (nullable)

  is_tax_inclusive           boolean         Price includes tax? (default false)

  tax_rate                   decimal(5,2)    Snapshotted rate (default 0)

  unit_price                 decimal(15,4)   Unit price (as entered)

  unit_price_exc_tax         decimal(15,4)   Calculated price excluding tax

  subtotal                   decimal(15,2)   quantity × unit_price_exc_tax

  tax_amount                 decimal(15,2)   subtotal × tax_rate

  total                      decimal(15,2)   subtotal + tax_amount

  notes                      text            Item-specific notes (nullable)

  timestamps                 \-\--           created_at, updated_at
  -------------------------- --------------- ----------------------------------------

*Traceability: supplier_order_item_id links invoice item back to order item
for verification.*

10.9.4 supplier_payments

Payment records for supplier invoices with proof uploads.

  --------------------- ---------------- ------------------------------------
  **Column**            **Type**         **Notes**

  id                    bigint PK        Auto-increment primary key

  supplier_invoice_id   bigint FK        Links to supplier_invoices

  amount                decimal(15,2)    Payment amount

  currency              char(3)          Payment currency

  original_amount       decimal(15,2)    If different currency (nullable)

  original_currency     char(3)          Original currency

  exchange_rate         decimal(20,10)   Exchange rate used

  paid_at               date             Date payment made

  method                varchar(50)      Payment method

  reference             varchar(100)     Payment reference

  notes                 text             Journal entry notes

  recorded_by           bigint FK        User who recorded

  timestamps            \-\--            created_at, updated_at
  --------------------- ---------------- ------------------------------------

10.10 Shipments (Manual Journaling)

**Manual journaling approach.** Each supplier uses their own shipper - we
record what they tell us, not manage logistics. Priority: status updates,
tracking reference, carrier info.

10.10.1 shipments

  --------------------- --------------- ---------------------------------------
  **Column**            **Type**        **Notes**

  id                    bigint PK       Auto-increment primary key

  request_id            bigint FK       Links to requests

  supplier_order_id     bigint FK       Links to supplier_orders (nullable)

  type                  varchar(30)     inbound (from supplier), outbound (to
                                        buyer)

  status                varchar(30)     pending, in_transit, delivered,
                                        partial, failed (PRIMARY FIELD)

  carrier               varchar(100)    Supplier's shipper (JNE, JNT, SiCepat)

  tracking_number       varchar(100)    For reference/lookup, not live tracking

  shipped_at            date            Ship date (manually recorded)

  expected_delivery     date            Expected arrival

  delivered_at          date            Actual delivery date

  notes                 text            Journal entry notes

  recorded_by           bigint FK       User who recorded

  timestamps            \-\--           created_at, updated_at
  --------------------- --------------- ---------------------------------------

*Attachments (BOL, packing list, POD) linked via polymorphic attachments
table.*

**Workflow:**
1. Supplier ships goods, sends tracking info (WA/email)
2. Admin creates shipment record with carrier + tracking number
3. Admin updates status as supplier/shipper provides updates
4. On receipt, admin records what was actually received

10.10.2 shipment_items

Tracks what was actually received vs ordered. Essential for discrepancy
detection.

  ----------------------- --------------- -------------------------------------
  **Column**              **Type**        **Notes**

  id                      bigint PK       Auto-increment primary key

  shipment_id             bigint FK       Links to shipments

  supplier_order_item_id  bigint FK       Links to supplier_order_items

  quantity_shipped        decimal(15,3)   What supplier says they shipped

  quantity_received       decimal(15,3)   What we actually received (nullable)

  condition               varchar(30)     good, damaged, rejected

  notes                   text            Discrepancy notes

  timestamps              \-\--           created_at, updated_at
  ----------------------- --------------- -------------------------------------

**Why this matters:**
- Ordered 100, supplier says shipped 100, but we received 95 → shortage claim
- Received 100, but 5 damaged → supplier dispute
- Partial shipments: Ship 50 now, 50 next week

10.11 Attachments via Spatie Media Library

**REUSES EXISTING RELATICLE INFRASTRUCTURE**

File uploads are handled via Spatie Media Library, which is already installed
in Relaticle. ERP entities that need attachments implement the `HasMedia` trait.

10.11.1 Media Collections

Instead of a custom `attachments` table, ERP models define media collections:

  -------------------- ------------------------------------------------------
  **Model**            **Collections**

  BuyerPayment         `payment_proof` (required proof of payment)

  SupplierPayment      `payment_proof` (required proof of payment)

  Shipment             `shipping_doc` (BOL, packing list, airway bill)
                       `pod` (proof of delivery)

  SupplierQuote        `quote_doc` (supplier quote PDF)

  BuyerInvoice         `invoice_copy` (scanned invoice)
  -------------------- ------------------------------------------------------

10.11.2 Implementation

```php
// Model implements HasMedia interface and InteractsWithMedia trait
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class BuyerPayment extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('payment_proof')
            ->singleFile(); // Only one proof per payment
    }
}

// Usage
$payment->addMedia($file)->toMediaCollection('payment_proof');
$payment->getMedia('payment_proof');
$payment->getFirstMediaUrl('payment_proof');
```

*Note: This reuses the existing `media` table from Spatie Media Library.
No new tables needed.*

10.12 Activity & Audit Logging

The system uses **two complementary logging approaches**:

| Log Type | Purpose | Scope |
|----------|---------|-------|
| `request_activities` | User-friendly timeline on Request detail page | Request events only |
| `activity_log` (Spatie) | System-wide audit trail for compliance | All entities |

10.12.1 request_activities (Request Timeline)

User-facing activity stream shown on the Request detail page.

  --------------- --------------- ----------------------------------------
  **Column**      **Type**        **Notes**

  id              bigint PK       Auto-increment primary key

  request_id      bigint FK       Links to requests

  user_id         bigint FK       User who performed action

  type            varchar(50)     Activity type (see list below)

  description     text            Human-readable description

  properties      jsonb           Additional data (old/new values, IDs)

  created_at      timestamp       When activity occurred
  --------------- --------------- ----------------------------------------

Activity types: request_created, stage_change, supplier_quote_received,
supplier_selected, buyer_quote_created, buyer_quote_sent,
buyer_quote_revised, buyer_quote_accepted, buyer_quote_rejected,
buyer_quote_extended, buyer_order_created, supplier_order_sent,
shipment_update, invoice_created, invoice_sent, payment_received,
payment_made, note_added, request_closed

10.12.2 System Audit Log (Spatie Activity Log)

**INSTALL spatie/laravel-activitylog PACKAGE**

Provides system-wide audit trail for compliance and auditing:

```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
php artisan migrate
```

**Audit Log Captures:**

| Event Type | What's Logged |
|------------|---------------|
| **Create** | All field values of new record |
| **Update** | Old values → New values (only changed fields) |
| **Delete** | All field values before deletion |
| **Login/Logout** | User, IP address, user agent, timestamp |
| **State Changes** | Quote sent, order confirmed, invoice paid, etc. |

**Entities with Audit Logging:**

- Buyers, Suppliers, Articles
- Requests, Request Items
- Supplier Quotes, Buyer Quotes
- Supplier Orders, Buyer Orders
- Supplier Invoices, Buyer Invoices
- Payments (buyer and supplier)
- Shipments
- Exchange Rates, Tax Codes

**Audit Log Admin View Requirements:**

| Feature | Description |
|---------|-------------|
| List view | Paginated table of all activities |
| Filters | Date range, user, entity type, action (create/update/delete) |
| Search | By entity name, user name, description |
| Detail view | Show old/new values side-by-side |
| Export | CSV export for compliance reports |
| Retention | Configurable retention period (default: 2 years) |

**Audit Query Examples:**

- "Show all changes made by User X in the last 30 days"
- "Show all edits to Buyer Y"
- "Show all invoices marked as paid this month"
- "Show all login attempts from IP address Z"

10.13 Roles & Permissions via Spatie Permission

**INSTALL spatie/laravel-permission PACKAGE**

RBAC is handled via Spatie Laravel Permission package, which provides:
- Role and permission management
- Role-permission assignment
- User-role assignment
- Gate integration for authorization

10.13.1 Installation

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

10.13.2 User Model Setup

```php
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

10.13.3 Default Roles

  --------------- ---------------------------------------------------
  **Role**        **Description**

  superadmin      Full access to all features (Gate::before bypass)

  admin           Manage all ERP entities except system settings

  sales           Create/manage requests, quotes, orders

  finance         Manage invoices, payments, P&L reports

  viewer          Read-only access to all ERP data
  --------------- ---------------------------------------------------

10.13.4 Permission Naming Convention

Permissions follow the pattern `erp.{resource}.{action}`:

  ------------------------------ ---------------------------------------
  **Permission**                 **Description**

  erp.requests.create            Create new requests
  erp.requests.edit              Edit existing requests
  erp.quotes.send                Send quotes to buyers
  erp.payments.record            Record buyer/supplier payments
  erp.invoices.create            Create invoices
  ------------------------------ ---------------------------------------

10.13.5 Seeder

```php
// database/seeders/ErpPermissionsSeeder.php
$roles = ['superadmin', 'admin', 'sales', 'finance', 'viewer'];
$permissions = [
    'erp.requests.create', 'erp.requests.edit', 'erp.requests.view',
    'erp.quotes.create', 'erp.quotes.send', 'erp.quotes.view',
    'erp.orders.create', 'erp.orders.view',
    'erp.payments.record', 'erp.payments.view',
    'erp.invoices.create', 'erp.invoices.view',
    // ... more permissions
];
```

*Note: This reuses Spatie's existing tables (roles, permissions, model_has_roles,
model_has_permissions, role_has_permissions). No new tables needed.*

10.14 Computed Values (Model Accessors)

These values are computed via Laravel model accessors, not stored:

  -------------- --------------------- --------------------------------------
  **Model**      **Accessor**          **Calculation**

  Buyer          available_credit      credit_limit - outstanding_balance

  Buyer          outstanding_balance   sum(unpaid invoice amounts)

  Request        buyer_total           buyer_order.total or latest
                                       buyer_quote.total

  Request        supplier_cost         sum(supplier_orders.total_in_base)

  Request        gross_margin          buyer_total - supplier_cost

  Request        margin_percent        (gross_margin / buyer_total) × 100

  Request        days_active           now() - created_at

  BuyerInvoice   amount_paid           sum(payments.amount)

  BuyerInvoice   amount_outstanding    amount - amount_paid

  BuyerInvoice   days_overdue          if overdue: now() - due_at
  -------------- --------------------- --------------------------------------

10.15 Recommended Indexes

-   **requests:** (buyer_id), (project_id), (stage), (created_at), (request_number)

-   **projects:** (buyer_id), (status)

-   **buyer_invoices:** (request_id), (status, due_at) for aging queries

-   **supplier_quotes:** (request_id), (supplier_id, status)

-   **buyer_quotes:** (request_id, version), (status)

-   **articles:** GIN on attributes for JSONB queries

-   **request_activities:** (request_id, created_at DESC)

-   **attachments:** (attachable_type, attachable_id), (type)

-   **exchange_rates:** (base_currency, target_currency, effective_date)

-   **tags:** (slug), (name)

10.16 Value Locking Rules

**Quotes are the negotiation phase.** All values remain editable while a
quote is active.

**Orders lock values.** Once a quote becomes an order, values are frozen
for accounting integrity.

  Field             On Quote           On Order
  ----------------- ------------------ ------------------
  Exchange rate     Editable           Locked
  Unit prices       Editable           Locked
  Quantities        Editable           Locked
  Tax percent       Editable           Locked
  Payment terms     Editable           Locked

**Why this matters:**
- Quotes can be revised multiple times during negotiation
- Exchange rates can fluctuate; update until deal is confirmed
- Once buyer PO is received and supplier POs issued, values must not change
- Order amendments require creating new documents (credit notes, revised POs)

11\. Implementation Summary

11.1 Critical Success Factors

6.  **Single-Page Request Management:** 80% of work on request detail
    page without navigation

7.  **Inline Entity Creation:** Create buyers, suppliers, articles
    without leaving request flow

8.  **Visual Document Chain:** Quote-to-order-to-payment chain visible
    at a glance

9.  **Per-Request Profitability:** Gross margin calculated and displayed
    in real-time

10. **Credit Limit Enforcement:** Warn before exceeding buyer credit
    limits

11. **Multi-Currency Support:** Handle different supplier currencies
    with conversion tracking

12. **Quote Extension Tracking:** Full audit trail when extending quote
    validity

13. **Proof-Based Journaling:** Required file uploads for payments and
    deliveries

11.2 Relaticle Infrastructure Reuse

The following components are REUSED from the existing Relaticle CRM:

  ----------------------- -------------------------------------------------
  **Component**           **How We Use It**

  Spatie Media Library    File attachments for payment proofs, shipping
                          docs, PODs (existing `media` table)

  Spatie Settings         ERP configuration via `ErpSettings` class
                          (default currency, tax rate, quote validity)

  Custom Fields           Extensible fields on Buyer, Supplier, Article,
                          Request via `UsesCustomFields` trait

  Multi-tenancy           Team-based isolation via `HasTeam` trait
                          (all ERP entities scoped to team)

  Tasks/Notes             Polymorphic tasks and notes on Requests
                          (add to morphMap in AppServiceProvider)
  ----------------------- -------------------------------------------------

**Tables NOT Created (Reusing Existing)**

  Originally Proposed     Using Instead
  ----------------------- -----------------------------------------------
  `attachments`           Spatie Media Library (`media` table)
  `settings`              Spatie Settings (class-based, no table)
  `roles`                 Spatie Permission tables
  `permissions`           Spatie Permission tables
  `role_permissions`      Spatie Permission tables
  `user_roles`            Spatie Permission tables

**Result: 30 new tables (v3.2)**

```
Foundation:        tags, taggables, currencies, exchange_rates, tax_codes (5)
Master Data:       buyers, suppliers, articles, supplier_articles (4)
Requests:          projects, requests, request_items (3)
Supplier Quoting:  supplier_quotes, supplier_quote_items (2)
Buyer Quoting:     buyer_quotes, buyer_quote_items, buyer_quote_extensions (3)
Supplier Orders:   supplier_orders, supplier_order_items (2)
Buyer Orders:      buyer_orders, buyer_order_items (2)
Finance:           buyer_invoices, buyer_invoice_items, buyer_payments,
                   supplier_invoices, supplier_invoice_items, supplier_payments (6)
Shipments:         shipments, shipment_items (2)
Activity:          request_activities (1)
```

11.3 Development Roadmap

  --------------- ----------------------------------------
  **Phase**       **Deliverables**

  **0.            Install Spatie Permission, create
  Prerequisites** ErpSettings class, setup morphMap for
                  ERP entities, add HasRoles to User

  **1.            Tags, currencies, exchange rates,
  Foundation**    buyers (with company_id), suppliers
                  (with company_id), articles

  **2. Request    Projects, Requests with stages,
  Management**    request items with vague capture

  **3. Quoting**  Multiple supplier quotes, consolidated
                  buyer quotes, quote extensions, tax
                  calculation, margin display

  **4. Orders**   Consolidated buyer orders, multiple
                  supplier orders, shipment tracking
                  with Media Library attachments

  **5. Finance**  Buyer invoices with line items,
                  credit notes as invoice type,
                  multiple supplier invoices with items,
                  payment recording with proofs,
                  multi-currency P&L, activity logging

  **6. Polish**   Dashboard with KPIs, quote expiration
                  alerts, PDF generation, additional
                  role permissions
  --------------- ----------------------------------------

12\. ERP Terminology Reference

For users familiar with traditional ERP systems, the UI can optionally
display standard ERP terminology labels. These appear as small labels
or tooltips alongside the friendly names used throughout the system.

  ----------------------- ------------------------- ---------------------
  **Friendly Name**       **ERP Term**              **Abbreviation**

  Buyer Quote             Sales Quotation           SQ

  Buyer Order             Sales Order               SO

  Buyer Invoice           Accounts Receivable       AR

  Supplier Quote          Purchase Quotation        PQ / Quote Request

  Supplier Order          Purchase Order            PO

  Supplier Invoice        Accounts Payable          AP

  Inbound Shipment        Goods Receipt             GR

  Outbound Shipment       Delivery Order            DO

  Payment (Buyer)         Receipt / Collection      -

  Payment (Supplier)      Disbursement              -

  Request                 Job / Work Order          WO

  Project                 Project (group of WOs)    PRJ
  ----------------------- ------------------------- ---------------------

UI Implementation: Small badge or tooltip showing ERP term, e.g.:
"Buyer Quote [SQ]" or hover tooltip "Also known as: Sales Quotation (SQ)"

13\. Supplier Confidentiality

IMPORTANT: Buyer-facing documents NEVER show supplier information.

-   Buyer quotes show items WITHOUT supplier names
-   Buyer invoices show items WITHOUT supplier reference
-   The "Supplier" column in buyer_quote_items is for INTERNAL use only
-   supplier_quote_item_id links are for margin calculation, not display
-   Buyers only see: item description, quantity, unit, and their price

This protects supplier relationships and prevents buyers from
bypassing the middleman by contacting suppliers directly.

*\-\-- End of Document \-\--*
