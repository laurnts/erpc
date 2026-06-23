# ERPC — Slide Deck Reference

> Use this document as the source for a PowerPoint presentation.  
> Each **Slide** block = one slide. Keep bullets on-slide; use **Speaker notes** for detail.

---

## Suggested Deck Outline (~20 slides)

| # | Slide title | Section |
|---|-------------|---------|
| 1 | Title | Intro |
| 2 | What is ERPC? | Intro |
| 3 | Who is it for? | Intro |
| 4 | End-to-end trading lifecycle | Workflow overview |
| 5 | Request Management | Workflow |
| 6 | Request View — 8 stages | Workflow |
| 7 | Supplier Quoting & QE | Workflow |
| 8 | Buyer Quoting | Workflow |
| 9 | Profit & Loss (PNL) | Workflow |
| 10 | Orders & Approvals | Workflow |
| 11 | Invoicing & Shipments | Workflow |
| 12 | Document Management | Documents |
| 13 | Acceptance Report | Approvals |
| 14 | Master Data | Data |
| 15 | CRM Capabilities | CRM |
| 16 | Multi-Team & Roles | Platform |
| 17 | Email & Notifications | Platform |
| 18 | Import / Export & Automation | Platform |
| 19 | Technology Stack | Closing |
| 20 | Summary / Q&A | Closing |

---

## SLIDE 1 — Title

**Title:** ERPC  
**Subtitle:** Enterprise Trading Platform

**On slide:**
- Modern ERP for trading companies
- CRM + procurement + sales in one platform

---

## SLIDE 2 — What is ERPC?

**Title:** What is ERPC?

**On slide:**
- ERP system built for **trading companies**
- Combines **CRM** with **procurement & sales** workflows
- Manages the full deal lifecycle in one place

**Speaker notes:**  
From buyer inquiry → supplier sourcing → quoting → ordering → invoicing → payment tracking.

---

## SLIDE 3 — Who is it for?

**Title:** Built for Trading Businesses

**On slide:**
- Companies that **source from multiple suppliers**
- Companies that **sell to multiple buyers**
- **Central Purchasing** teams managing approvals
- Multi-team organizations needing isolated workspaces

---

## SLIDE 4 — End-to-End Trading Lifecycle

**Title:** One Workflow, Start to Finish

**On slide (flow — use arrows or diagram in PPT):**

```
Buyer Inquiry → Supplier Sourcing → Quoting → Ordering → Delivery → Invoicing → Payment
```

**Key stages:**
- Request → Quote → Evaluate → Order → Receive → Ship → Invoice → Complete

**Speaker notes:**  
Every deal is tracked as a **Request** that moves through controlled stages with approval gates.

---

## SLIDE 5 — Request Management

**Title:** Request Management

**On slide:**
- Track buyer inquiries from first contact to fulfillment
- Single **Request View** as the operational hub
- Stage advancement tied to approval rules (QE, PNL, supplier orders)
- **Information Flow widget** — contextual guide per tab

**Speaker notes:**  
The Request View is the main screen users work from daily. Tabs unlock based on stage and approvals.

---

## SLIDE 6 — Request View: 8 Stages

**Title:** Request View — 8 Tabs

**On slide (use numbered icons or timeline layout):**

| Step | Tab |
|------|-----|
| 1 | Requested Items |
| 2 | Supplier Quotes |
| 3 | Buyer Quotes |
| 4 | Purchases (Supplier Orders) |
| 5 | Goods Receive |
| 6 | Invoices (Buyer Orders) |
| 7 | Inbound Shipments |
| 8 | Completion Report |

**Speaker notes:**  
Information Flow widget at the bottom updates to show guidance for the active tab.

---

## SLIDE 7 — Supplier Quoting & Quotation Evaluation

**Title:** Supplier Quoting & QE

**On slide:**

**Supplier Quoting**
- Collect quotes from multiple suppliers
- Compare options side by side

**Quotation Evaluation (QE)**
- Internal comparison document with item & supplier details
- Multi-level approval workflow
- Upload documents · Download PDF
- Approved via Acceptance Report → status set to **Approved**

---

## SLIDE 8 — Buyer Quoting

**Title:** Buyer Quoting

**On slide:**
- Consolidated quotes with **margin analysis**
- Payment terms validation (percentages must total 100%)
- Prepayment support (percentage or fixed amount)
- **+Tax sync** for service items with child line items
- **Buyer PO upload** when quote is Accepted
- **Expiry alerts** — UI warning, email & in-app notifications

**Speaker notes:**  
Daily jobs notify creators before expiry (7/3/1 days) and alert buyers + key accounts after expiry.

---

## SLIDE 9 — Profit & Loss (PNL)

**Title:** Profit & Loss (PNL)

**On slide:**
- PNL documents with items grouped by supplier
- **Cost · Sell · Margin** analysis
- Approval workflow (same senior roles as QE)
- Document upload & PDF export
- Key Account approval via Acceptance Report

---

## SLIDE 10 — Order Processing & Approvals

**Title:** Orders & Supplier Approval

**On slide:**

**Order Processing**
- Buyer purchase orders
- Supplier purchase orders

**Supplier Order Approval**
- Minimum **2 approvals** required before sending
- Approvers: Dept Head of Sales · Deputy Director · Director
- Document upload from list or order view

---

## SLIDE 11 — Invoicing & Shipments

**Title:** Invoicing & Shipments

**On slide:**

**Invoicing**
- Buyer & supplier invoices
- Payment tracking

**Shipment Tracking**
- Delivery status & logistics monitoring
- **Delivery Order (DO) PDF** generation
- **PIC Contact** on shipments — shown on DO PDF

---

## SLIDE 12 — Document Management

**Title:** Document Storage & Uploads

**On slide:**
- Dedicated folders per feature (quotes, orders, QE, PNL, etc.)
- Organized media library with unique file IDs
- Upload from entity view pages & approval lists
- Backward compatible with legacy file paths

**Supported document types:**
- Supplier Quotes · Buyer Quotes · Supplier Orders
- Goods Receive · Completion Reports · QE · PNL

---

## SLIDE 13 — Acceptance Report

**Title:** Acceptance Report — Central Approval Hub

**On slide:**

| Document | Approved by |
|----------|-------------|
| Payment documents (completion reports) | Finance |
| QE / PNL / Supplier Order documents | Key Account |

**Actions:** View Document · Approve  
**Result:** Entity status → **Approved**

**Speaker notes:**  
Located under **Approval > Acceptance Report**. Shows document name, source, request number, buyer, payment terms, timestamps.

---

## SLIDE 14 — Master Data

**Title:** Core Entities

**On slide (icon grid works well):**

| Entity | Purpose |
|--------|---------|
| **Companies** | Buyers & suppliers |
| **Articles** | Product catalog |
| **Projects** | Group large deals |
| **Currencies** | Multi-currency support |
| **Tax Codes** | Per-item tax rules |
| **Team Members** | CP staff & approvers |

---

## SLIDE 15 — CRM Capabilities

**Title:** CRM Built In

**On slide:**
- **People / Contacts** — linked to companies
- **Opportunities** — sales pipeline
- **Tasks & Notes** — activity tracking
- **AI Summaries** — AI-powered record summaries

**Speaker notes:**  
Contacts include name, phone, email. Reused across shipments (PIC), companies, and CRM.

---

## SLIDE 16 — Multi-Team & Roles

**Title:** Teams, Roles & Access Control

**On slide:**

**Multi-Team**
- Isolated workspace per team (tenant)
- Shared navigation · record-level permissions

**Roles**
| Role | Access |
|------|--------|
| Administrator | Full access |
| Editor | Read · Create · Update |
| Central Purchasing | Read · Create · Update + sub-roles |

**Central Purchasing sub-roles:**
- Key Account · Dept Head of Sales · Deputy Director · Director

**Speaker notes:**  
Key Account prepares QE/PNL and approves documents. Senior roles approve QE, PNL, and supplier orders.

---

## SLIDE 17 — Email & Branding

**Title:** Email Settings & Templates

**On slide:**
- Template library per document type
- Dynamic variables (`buyer_name`, `quote_number`, …)
- Team SMTP with encrypted credentials
- Logo & signature branding
- Per-template sender, CC, BCC
- Test email before go-live

---

## SLIDE 18 — Import, Export & Automation

**Title:** Data Management & Automation

**On slide:**

**Import + Export**
- People · Buyers · Suppliers · Articles

**Export only**
- QE · PNL · Buyer/Supplier Quotes · Buyer/Supplier Orders

**Scheduled jobs**
| Time | Action |
|------|--------|
| 08:00 daily | Expiring quote reminders (7 / 3 / 1 days) |
| 08:30 daily | Expired quote alerts to buyer & key accounts |

**Also:** Custom fields · Role-based access · CSV/Excel

---

## SLIDE 19 — Technology Stack

**Title:** Built on Modern Technology

**On slide (logo row recommended):**

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.4 · Laravel 12 |
| Admin UI | Filament 5 · Livewire 4 |
| Database | PostgreSQL 15+ |
| Frontend | Tailwind CSS 4 |
| Queue | Redis (optional) |

---

## SLIDE 20 — Summary

**Title:** ERPC at a Glance

**On slide:**
- ✅ End-to-end trading workflow (8-stage Request View)
- ✅ Multi-level approval (QE · PNL · Supplier Orders)
- ✅ CRM + master data in one platform
- ✅ Document management & Acceptance Report
- ✅ Multi-team · role-based access · email automation
- ✅ Import/export & scheduled notifications

**Closing line:**  
*One platform for trading companies — from inquiry to payment.*

---

## Design Tips for PowerPoint

1. **One idea per slide** — use Speaker notes for extra detail, not the slide body.
2. **Max 5–6 bullets** per slide; shorten further if using icons or a diagram.
3. **Slide 4 & 6** — use a horizontal timeline or chevron process graphic.
4. **Slide 13 & 16** — use a two-column layout for tables.
5. **Slide 19** — vendor logos (Laravel, PostgreSQL, etc.) add visual polish.
6. **Consistent section colors:**
   - Workflow slides → one accent color
   - Platform slides → another accent color
7. **Optional appendix slides** (not in main deck): detailed role matrix, full stage list, import/export entity list.

---

## License

AGPL-3.0
