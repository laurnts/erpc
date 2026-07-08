# ERPC — 17-Slide Deck (AI / PowerPoint Source)

> **Purpose:** Feed this file to Gemini, ChatGPT, or similar tools to generate a **maximum 17-slide** presentation.  
> **Full detail:** see [`FEATURES.md`](../FEATURES.md) in the repo root.  
> **Audience:** Trading company stakeholders, Central Purchasing, management (non-technical).

---

## How to use with Gemini / AI

1. Upload this file (or paste it in full).
2. Use this prompt:

```text
Create a 17-slide presentation from DECK_17_SLIDES.md.

Rules:
- Exactly 17 slides, use the titles and "On slide" bullets as-is (you may shorten slightly for fit).
- Max 4 bullets per slide, large-font friendly.
- Put extra detail in speaker notes only.
- Audience: Indonesian trading company, Central Purchasing and GM.
- Simple English; explain QE = Quotation Evaluation, PNL = Profit & Loss, PO = Purchase Order on first use.
- Do not add slides beyond 17.
- Output for Google Slides or PowerPoint (one slide block each).
```

3. Recreate flow diagrams as simple arrows (AI cannot use Mermaid in PPT directly).

---

## Slide map (17 total)

| # | Title |
|---|-------|
| 1 | ERPC — Enterprise Trading Platform |
| 2 | The Problem Today |
| 3 | What Is ERPC? |
| 4 | Who Uses ERPC? |
| 5 | One Deal = One Request |
| 6 | The Journey: Inquiry to Payment |
| 7 | Auto Tender (Multi-Vendor) |
| 8 | Compare Suppliers — QE |
| 9 | Buyer Quote & Margin (PNL) |
| 10 | Orders & Dual Approval |
| 11 | Receive, Ship & Close |
| 12 | Documents & Acceptance Report |
| 13 | Tax (PKP) & Multi-Currency |
| 14 | Master Data Made Easy |
| 15 | CRM, Dashboard & Reminders |
| 16 | ERPC vs SAP |
| 17 | Summary |

---

## SLIDE 1 — ERPC — Enterprise Trading Platform

**Subtitle:** Procurement middleware for trading companies

**On slide:**
- One platform from buyer ask → to payment
- Built for companies that **buy from suppliers** and **sell to buyers**
- Central Purchasing, approvals, and documents in one place

**Visual:** Logo + simple tagline. Optional subtitle: *Jembatan operasional sebelum SAP*

**Speaker notes:** ERPC = Enterprise Trading Platform. Not replacing SAP finance — the daily operations layer for trading teams.

---

## SLIDE 2 — The Problem Today

**On slide:**
- Prices and margins stuck in **Excel**
- Approvals lost in **email** — who signed?
- Documents scattered in **folders**
- **SAP** is powerful but too heavy for daily buying work

**Visual:** Icons Excel + Email + Folder with red X

**Speaker notes:** Staff copy data manually. Managers cannot see margin before money is committed.

---

## SLIDE 3 — What Is ERPC?

**On slide:**
- **B2B procurement middleware** — not full corporate ERP
- One **Request** screen for the whole deal
- Quote → tender → approve → order → deliver → close
- Works **with SAP** — ops here, finance there if needed

**Visual:** Box “ERPC = daily ops” → optional arrow → “SAP = finance”

**Speaker notes:** Positioning: jembatan sebelum masuk SAP. CP teams live here every day.

---

## SLIDE 4 — Who Uses ERPC?

**On slide:**
- **Central Purchasing (CP)** — prepares quotes, orders, documents
- **Key Account** — owns buyer; approves documents
- **Senior management** — approves QE, PNL, supplier PO (min. 2 for PO)
- **Finance** — payment documents, credit limits

**Visual:** 4 role icons with labels

**Speaker notes:** Multi-team workspaces; each person sees what their role allows.

---

## SLIDE 5 — One Deal = One Request

**On slide:**
- **8 tabs** on Request View — one chapter per step
- Items → Supplier quotes → Buyer quote → Purchases → GR → Invoices → Shipment → Completion
- **Help widget** at bottom tells staff what to do next
- Gates: QE approved, PNL approved, buyer PO accepted

**Visual:** Numbered tabs 1–8 horizontal

**Speaker notes:** “Invoices” tab = buyer orders in the system. Service requests skip shipment tab; use completion instead.

---

## SLIDE 6 — The Journey: Inquiry to Payment

**On slide:**
1. Inquiry (request + items)
2. Tender (supplier quotes)
3. QE approved (internal compare)
4. Buyer quote sent
5. PNL approved (margin OK)
6. PO approved & sent
7. Goods received → shipped (goods)
8. Complete & payment docs

**Visual:** Chevron arrow 1→2→3→…→8

**Speaker notes:** Ten-step business flow in FEATURES.md; this slide is the executive summary.

---

## SLIDE 7 — Auto Tender (Multi-Vendor)

**On slide:**
- One product line matched to catalog
- System finds **all linked suppliers**
- **Auto-creates** a quote request per vendor
- No copy-paste quote requests to 5 vendors manually

**Visual:** 1 item in center → arrows to Supplier A, B, C

**Speaker notes:** Triggers when request moves to Awaiting Supplier Response. Email can notify suppliers.

---

## SLIDE 8 — Compare Suppliers — QE

**On slide:**
- **QE** (Quotation Evaluation) = internal price comparison
- Not sent to customer — for management decision
- PDF + upload signed document
- **Key Account** approves in Acceptance Report

**Visual:** Table: Supplier A vs B vs C (price, PKP yes/no)

**Speaker notes:** Senior roles receive email when QE needs approval. Tab access blocked until QE approved (or quote selected bypass).

---

## SLIDE 9 — Buyer Quote & Margin (PNL)

**On slide:**
- **Buyer quote** — offer to customer with margin %
- Payment terms must total **100%**; prepayment rules
- Upload **buyer PO** when quote accepted
- **PNL** — cost vs sell vs margin **before** we order

**Visual:** Simple margin table: Cost | Sell | Margin

**Speaker notes:** Quote expiry reminders (7/3/1 days). PNL approval required before purchase tabs unlock.

---

## SLIDE 10 — Orders & Dual Approval

**On slide:**
- **Buyer order** — what customer bought (Invoices tab)
- **Supplier PO** — what we send to vendor
- PO needs **2 senior approvers** before email/PDF to supplier
- Cannot accidentally send large PO without sign-off

**Visual:** PO PDF with two “Approved by” signature lines

**Speaker notes:** Dept Head, Deputy Director, or Director — two different people. Approval menu: Supplier Orders.

---

## SLIDE 11 — Receive, Ship & Close

**On slide:**
- **Goods receive** — upload proof from supplier; approval queue
- **Shipment** — Delivery Order (DO) PDF; PIC on document
- **Completion report** — close job; payment evidence for finance

**Visual:** Warehouse → truck → checkmark

**Speaker notes:** Goods requests use inbound shipment. Service requests use completion path (no DO shipment tab).

---

## SLIDE 12 — Documents & Acceptance Report

**On slide:**
- Generate **PDF** (QE, PNL, PO, DO, quotes)
- Print / sign / **re-upload** to system
- **Acceptance Report** = one approval inbox
- KA approves QE/PNL/PO docs; Finance approves payment docs

**Visual:** Loop: Generate → Sign → Upload → Approved ✓

**Speaker notes:** Audit trail: who approved, when. No more signed PO lost in WhatsApp.

---

## SLIDE 13 — Tax (PKP) & Multi-Currency

**On slide:**
- **PKP supplier** — tax on PO and quotes
- **Non-PKP** — no tax on that vendor’s lines
- Tax per **line item**, not guessed in Excel later
- **Multi-currency** — USD, IDR, etc. with exchange rates

**Visual:** Two columns PKP | Non-PKP

**Speaker notes:** Supplier master flag `is_taxable`. Rates locked on orders for reporting.

---

## SLIDE 14 — Master Data Made Easy

**On slide:**
- Quick **new supplier** and **new article** (or import Excel)
- **One article → many suppliers** (multi-vendor catalog)
- Auto document numbers (REQ, PO, BQ…)
- **Projects** group many requests — see total spend per project

**Visual:** Article in center linked to 3 suppliers

**Speaker notes:** Master data grows with deals — no 6-month MDM project before first inquiry.

---

## SLIDE 15 — CRM, Dashboard & Reminders

**On slide:**
- **People, opportunities, tasks** — CRM beside trading
- **Dashboard** — active requests, expiring quotes, overdue payments
- **Auto reminders** — quotes, supplier silence, invoices
- Import/export buyers, suppliers, articles

**Visual:** Dashboard mock + bell icon

**Speaker notes:** Jobs run daily 08:00–10:00. CP starts day knowing what needs attention.

---

## SLIDE 16 — ERPC vs SAP

**On slide:**

| | ERPC | SAP |
|---|------|-----|
| Daily buying & selling | ✅ Easy | ❌ Heavy |
| Company finance / GL | Optional | ✅ Strong |
| Time to start | Weeks | Often years |
| Best for | CP team every day | Enterprise finance |

**One line:** *ERPC = jembatan operasional · SAP = backbone keuangan*

**Visual:** Bridge diagram — ERPC front, SAP back office

**Speaker notes:** Not either/or for many traders — ERPC for ops, SAP for ledger when ready.

---

## SLIDE 17 — Summary

**On slide:**
- ✅ One **Request** — whole deal in one place
- ✅ **Auto tender** — many suppliers, one click
- ✅ **QE · PNL · dual PO** — control before money moves
- ✅ **Documents & Acceptance Report** — audit-ready
- ✅ **Works with SAP** — procurement middleware

**Closing line:** *From customer ask to payment — simpler, safer, faster.*

**Visual:** Repeat journey arrow from Slide 6

**Speaker notes:** Q&A. Offer demo. Contact details.

---

## Design checklist

- Max **4 bullets** per slide; 24pt+ bullet font
- One idea per slide — do not merge Slide 8 and 9
- Repeat **journey arrow** on Slides 1, 6, and 17
- Use **photos/icons**, not paragraphs
- Optional appendix (not counted in 17): screenshot of Request View 8 tabs

---

## License

Same as project: AGPL-3.0
