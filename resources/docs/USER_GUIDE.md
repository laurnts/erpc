# Central Purchasing – User Guide

## Application Overview

The app is a **tenant-based** Central Purchasing panel. After login you choose a **team (tenant)**. The **home** view is the **Companies** list (Workspace). The sidebar is grouped into: **Team Members** (no group), **Workflow**, **Master Data**, **Approval**, **Finance**, **Workspace**, and **Settings**.

---

## 1. Team Members (Top-Level)

**Location:** Sidebar, no group (first item).

**Purpose:** Manage who belongs to the current team and their roles.

**Features:**

- **List** all team members with photo, name, email, role, and approver flag.
- **Roles:** Admin, Editor, Central Purchasing (with sub-roles; Finance users can be marked as **Approver**).
- **Filters:** By role, approver status.
- **Actions:** Invite, edit, remove members; manage roles and Central Purchasing role (e.g. Finance, Key Account).

**Typical use:** Add/remove users, set roles, and designate approvers for finance-related workflows.

---

## 2. Workflow

### 2.1 Requests

**Purpose:** Core procurement workflow. Each request is a buyer’s purchase request that moves through sourcing → quoting → ordering → delivery → closing.

**Features:**

- **List:** Request number, buyer, project, type (product/service), priority, stage, dates. Filters (stage, type, buyer, etc.) and search.
- **Create:** Select buyer, optional project, request type, priority, requested date; optional custom fields.
- **View (main screen):** Single request with **tabs** for each stage:
  1. **Items** – Request line items (products/services, quantities, etc.).
  2. **Supplier Quotes** – Quotes from suppliers; compare and select.
  3. **Buyer Quotes** – Quotes sent to the buyer; prepare and send.
  4. **Supplier Orders** – Purchase orders to suppliers (after buyer confirmation).
  5. **Goods Receive** – Record receipt of goods; attach documents.
  6. **Buyer Orders** – Orders placed with the buyer.
  7. **Shipments** – Shipment tracking and status.
  8. **Completion Reports** – Final/completion documentation.

**Stages (simplified):** Draft → Awaiting Supplier Response → Preparing Buyer Quote → Awaiting Buyer Confirmation → Preparing Supplier Order → Goods Receive → Awaiting Shipment → Shipped → Delivered → Invoiced → Paid → Completed (or Cancelled).

**Also on request:** Activities. Stage can auto-advance when you open the relevant tab (e.g. Supplier Orders).

**Typical use:** Create requests, maintain items, manage supplier/buyer quotes and orders, record goods receipt and shipments, and track through to completion.

### 2.2 Projects

**Purpose:** Group requests by buyer or initiative (e.g. a campaign or project).

**Features:**

- **List:** Name, description, status, associated buyer, dates. Filters and search.
- **Create/Edit:** Name, description, optional buyer, status, custom fields.
- **View:** Project details; often used as context when creating or filtering requests.

**Typical use:** Organise requests by buyer or project and link new requests to a project.

---

## 3. Master Data

### 3.1 Buyers

**Purpose:** Companies that are your customers (buyers). Used as the “sold-to” in requests and quotes.

**Features:**

- **List:** Name, domain, email, categories (tags), status. Filters and export.
- **Create/Edit:** Company name, domain, email, categories, linked people; custom fields.
- **View:** Buyer profile; relation managers for **People**, **Articles**, etc.

**Typical use:** Maintain buyer companies and their contacts; ensure every request has a buyer.

### 3.2 Suppliers

**Purpose:** Vendors you buy from. Used in supplier quotes and supplier orders.

**Features:**

- **List:** Name, code, contact info, categories. Filters and search.
- **Create/Edit:** Name, code, contact details, categories; custom fields.
- **View:** Supplier profile; **Articles** (products they supply) and other relations.

**Typical use:** Central list of suppliers; link articles to suppliers; select suppliers when adding supplier quotes.

### 3.3 Articles

**Purpose:** Products or services you request, quote, and order (item master data).

**Features:**

- **List:** Name, SKU, categories, suppliers, unit, price info. Filters, import/export.
- **Create/Edit:** Name, SKU, description, categories, unit of measure, suppliers, pricing; custom fields.
- **View:** Article details; **Suppliers** relation (which suppliers provide this article).

**Typical use:** Define products/services once; reuse in request items and in quotes/orders.

### 3.4 Categories

**Purpose:** Tags/categories for buyers, suppliers, articles, and filtering.

**Features:**

- **List:** Category name and usage.
- **Create/Edit:** Name and optional settings.

**Typical use:** Tag buyers (e.g. by segment), suppliers (e.g. by type), and articles (e.g. by product group).

---

## 4. Approval

Approval sections are for **review and sign-off** by authorised users (e.g. Finance approvers). Access may be role-based.

### 4.1 Credit Limit

**Purpose:** Review and approve **buyer credit limit** change requests (increase/decrease).

**Features:**

- **List:** Buyer, code, current limit, requested limit, status, requester, dates.
- **Actions:** Approve or reject with comment; view details.

**Typical use:** Finance reviews requested credit limit changes and approves or rejects them.

### 4.2 Acceptance Report

**Purpose:** Accept/reject **credit limit acceptance reports** (documents confirming acceptance of credit terms or similar).

**Features:**

- **List:** Reports linked to buyers/credit; status.
- **Actions:** Accept or reject; view document and details.

**Typical use:** Confirm that credit terms or limits have been formally accepted.

### 4.3 Goods Receive

**Purpose:** Approve **goods receive** batches (proof of receipt) before they are fully accepted in the workflow.

**Features:**

- **List:** Document name, request, dates, status.
- **Actions:** Approve or reject; view attached documents.

**Typical use:** Ensure goods receive documentation is checked before closing the order.

### 4.4 Quotation Evaluations

**Purpose:** Evaluate and approve **quotation evaluations** (comparison/selection of supplier quotes).

**Features:**

- **List:** QE number, request, key account, status, dates. Filters and export.
- **View:** Evaluation details; assign key account; add notes; approve/reject.

**Typical use:** Key account or approver reviews quote comparison and approves the chosen option.

### 4.5 Profit & Loss

**Purpose:** Review and approve **P&L** documents for requests or projects.

**Features:**

- **List:** P&L number, request, key account, status. Filters and export.
- **View:** P&L details; approve or reject; assign key account.

**Typical use:** Approve margin/P&L before or after order confirmation.

### 4.6 Supplier Orders (Approval)

**Purpose:** Approve **supplier purchase orders** (POs) before they are confirmed to the supplier.

**Features:**

- **List:** PO number, request, supplier, currency, total, confirmation date, status.
- **View:** Full PO details; approve or reject; optional attachments.

**Typical use:** Second pair of eyes on POs (amounts, supplier, request) before sending to supplier.

---

## 5. Finance

Standalone finance views for **transactions and documents** (often linked to requests).

### 5.1 Buyer Quotes

**Purpose:** All **buyer quotes** (quotes you send to buyers) in one place.

**Features:**

- **List:** Quote number, request, buyer, amounts, validity, status. Filters and export.
- **View/Edit:** Quote lines, pricing, validity; link to request.

**Typical use:** Find any buyer quote, check status, and adjust or re-send.

### 5.2 Buyer Orders

**Purpose:** All **buyer orders** (orders received from buyers) in one place.

**Features:**

- **List:** Order number, request, buyer, totals, dates. Filters and export.
- **View:** Order details; link to request and related supplier orders.

**Typical use:** Overview of what buyers have ordered and follow-up on requests.

### 5.3 Supplier Quotes

**Purpose:** All **supplier quotes** (quotes you receive from suppliers) in one place.

**Features:**

- **List:** Quote ref, request, supplier, amounts, status. Filters and export.
- **View:** Quote lines and details; link to request.

**Typical use:** Compare supplier quotes across requests or find a specific quote.

### 5.4 Supplier Orders

**Purpose:** All **supplier POs** (orders you place with suppliers) in one place.

**Features:**

- **List:** PO number, request, supplier, total, currency, confirmation date. Filters.
- **View:** PO details; link to request; often used for operational follow-up (not approval).

**Typical use:** See all POs, confirmations, and link back to the request.

### 5.5 Transactions

**Purpose:** **Buyer credit limit transactions** (e.g. usage, adjustments, history).

**Features:**

- **List/View:** Buyer, credit limit, transaction type, amount, date; often read-only or with limited actions.

**Typical use:** Check buyer credit usage and history for credit decisions.

---

## 6. Workspace

### 6.1 Companies

**Purpose:** All **companies** (buyers, suppliers, or neutral). This is the **default home** after login.

**Features:**

- **List:** Name, domain, type (buyer/supplier/both), people, tags. Filters and export.
- **Create/Edit:** Name, domain, company type (buyer/supplier), people, tags; custom fields.
- **View:** Company profile; **People** and **Articles** relation managers.

**Typical use:** Single place for all companies; mark as buyer/supplier; manage contacts (people) and linked data.

### 6.2 People

**Purpose:** **Contacts** (persons) linked to companies (buyers/suppliers).

**Features:**

- **List:** Name, phone, email, companies. Filters and export.
- **Create/Edit:** Name, phone, email, linked companies; custom fields.
- **View:** Person profile; **Buyers** relation (companies where they are linked).

**Typical use:** Maintain contact persons and assign them to companies used in requests and quotes.

---

## 7. Settings

Team-level configuration. Changes apply to the **current team (tenant)**.

### 7.1 General

**Purpose:** **Default behaviour and number prefixes** for the tenant.

**Includes:**

- **ERP defaults:** Default currency, quote validity (days), default payment terms (days), default margin (%).
- **Prefixes:** Request number, project number, buyer quote, buyer order, supplier order, shipment, buyer/supplier invoice, buyer/supplier payment.

**Typical use:** Set currency, payment terms, and margin; define how request/order numbers are generated.

### 7.2 Currencies

**Purpose:** **Currencies** used in quotes and orders.

**Features:**

- **List/Edit:** Code, name, symbol; active/inactive.
- **Create:** Add a new currency.

**Typical use:** Enable all currencies you need for buyer and supplier documents.

### 7.3 Exchange Rates

**Purpose:** **Exchange rates** (e.g. to base currency) for multi-currency reporting and conversion.

**Features:**

- **List:** Currency pair, rate, date. Create/Edit rate and effective date.

**Typical use:** Keep rates up to date for correct totals and reporting.

### 7.4 Tax Codes

**Purpose:** **Tax codes** (e.g. VAT, exempt) for quotes and invoices.

**Features:**

- **List/Edit:** Code name, rate or type; use in forms.

**Typical use:** Standardise tax handling across buyer quotes and supplier orders.

### 7.5 Unit Of Measures

**Purpose:** **Units of measure** (e.g. pcs, kg, m²) for articles and request items.

**Features:**

- **List/Edit:** Code, name; use in article and item forms.

**Typical use:** Consistent units across articles and request lines.

### 7.6 Emails (Email Settings)

**Purpose:** **SMTP and sender** settings for sending emails (e.g. quote emails, notifications).

**Includes:**

- **SMTP:** Host, port, encryption, username, password.
- **Sender:** From address and name.
- **Test:** Send test email to verify configuration.

**Typical use:** Configure outgoing email and test it.

### 7.7 Email Templates

**Purpose:** **Reusable email templates** (e.g. quote cover, order confirmation) with placeholders.

**Features:**

- **List:** Template name, type, last updated.
- **Create/Edit:** Name, subject, body (HTML/text); placeholders for buyer, request, link, etc.

**Typical use:** Standardise and speed up quote and order emails.

---

## 8. User Menu (Top Right)

- **Profile** – Edit your name, email, password, and profile photo.
- **API Tokens** (if enabled) – Create/revoke API tokens for integrations.
- **Switch team** – Change current tenant (if you have access to multiple teams).
- **Log out** – Sign out.

---

## 9. Dashboard Widgets (Home/Dashboard)

Depending on the tenant and role, the dashboard may show widgets such as:

- **Active Requests** – Requests in progress (e.g. by stage).
- **Quotes Expiring** – Buyer quotes nearing or past validity.
- **Awaiting Payment** – Invoiced or unpaid items.
- **Requires Attention** – Items needing action (e.g. approval, follow-up).
- **Pipeline by Stage** – Request counts by workflow stage.
- **Monthly Revenue** – Revenue or margin over time.
- **Request Information Flow** – Visual or summary of request flow.

---

## 10. Request Workflow (End-to-End)

1. **Master Data:** Create/link **Buyer**, **Supplier**, **Articles**, **Categories** and **People** as needed.
2. **Request:** Create a **Request** (buyer, optional project, type, items).
3. **Items:** In the request view, add **Items** (article, quantity, etc.).
4. **Supplier Quotes:** Add **Supplier Quotes**; compare and select.
5. **Buyer Quote:** Prepare **Buyer Quote** from the request; send to buyer (email if configured).
6. **Approvals:** Use **Approval** menus (Quotation Evaluation, Profit & Loss, Supplier Orders) as per your process.
7. **Buyer confirmation:** When the buyer confirms, create **Buyer Order** from the request.
8. **Supplier Order:** Create **Supplier Order(s)** from the request; submit for **Supplier Order Approval** if required.
9. **Goods Receive:** Record **Goods Receive** and, if used, complete **Goods Receive** approval.
10. **Shipments / Completion:** Update **Shipments** and **Completion Reports**; move request to **Completed** when done.
11. **Credit:** For buyers, use **Credit Limit** and **Acceptance Report** under Approval, and **Transactions** under Finance to manage and monitor credit.
