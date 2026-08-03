# Operational Day-to-Day Manual — Design

Date: 2026-08-03  
Status: Approved (brainstorming)  
Audience: Non-technical end users (internal staff, buyer portal, supplier portal)

## Problem

ERPC has strong product reference (`FEATURES.md`) and an English menu-map user guide (`resources/docs/USER_GUIDE.md`), but no **day-to-day operational handbook** for non-technical users that:

- Explains how to run procurement work end-to-end
- Covers **internal staff**, **buyer portal**, and **supplier portal** in one place
- Uses Bahasa Indonesia as the narrative language with English UI labels
- Combines short overviews with step-by-step task playbooks

## Decisions (locked)

| Decision | Choice |
|----------|--------|
| “Admin” audience | All internal staff who run procurement day-to-day (Central Purchasing, Key Account, Finance, senior approvers) — one internal operations part |
| File organization | One combined handbook with three clear parts |
| Language | Bahasa Indonesia primary; English terms for system/UI labels |
| Depth | Short overview per area + step-by-step for main daily tasks |
| Delivery | New `resources/docs/OPERATIONAL_MANUAL.md`; keep `USER_GUIDE.md` unchanged for now |
| PDF / Settings download | Out of scope for v1 (continue pointing at `USER_GUIDE.md` until a follow-up) |

## Goals / Non-Goals

### Goals

- One bilingual operational handbook covering Internal, Buyer, and Supplier day-to-day work
- Task playbooks a new user can follow without developer help
- Accurate mapping to current verified product behavior (`FEATURES.md` §§2, 5, 6)
- Clear role callouts (CP / KA / Senior / Finance / Buyer / Supplier)

### Non-Goals (v1)

- Changing PDF download controller or Settings link
- Rewriting or deleting `USER_GUIDE.md`
- In-app Documentation module pages
- Screenshots / screen recordings
- System Admin (`sysadmin`) panel documentation
- Deep public-catalog merchandising guide (brief mention only)
- API / env / developer setup

## Deliverable

**Single file:** `resources/docs/OPERATIONAL_MANUAL.md`

Optional later (not v1):

- Point `UserGuideDownloadController` at the new manual and rename PDF filename
- Archive or shorten `USER_GUIDE.md` to a “menu map” appendix

## Document structure

```
0. Pengantar
1. Operasional Internal (staff)
2. Portal Buyer
3. Portal Supplier
4. Lampiran (glossary, stages, approval matrix, FAQ, known limits)
```

### Content pattern per area

1. Short overview — apa ini / kenapa penting
2. Step-by-step playbooks — “Bagaimana caranya…”
3. Tips / common mistakes where useful

### Writing conventions

- Section titles in Bahasa Indonesia; menu/button labels remain English as on screen
- Numbered steps; one action per step
- Each playbook opens with: **Siapa:** · **Kapan:** · **Hasil:**
- No code, APIs, env vars, or developer jargon
- Known UI gaps stated in plain language (e.g. credit note belum tersedia di layar)

## Part 0 — Pengantar

- What ERPC is (procurement/trading operations, not full ERP)
- Which login to use: internal app vs Buyer Portal vs Supplier Portal
- Team/tenant switch (internal only)
- How to use this handbook

## Part 1 — Operasional Internal

### Overview blocks

- Dashboard widgets (what needs attention today)
- Master Data: Buyers, Suppliers, Articles, Categories, People, Projects
- Workflow: Requests & Projects
- Approval menus: QE, PNL, Supplier Orders, Credit Limit Acceptances, Goods Receive, Credit Limit Requests, Registrations
- Finance lists (cross-request visibility)
- Settings staff may touch: currencies, exchange rates, tax codes, UoM; SMTP/templates as “tanya Admin / setup awal”

### Step-by-step playbooks

| # | Task |
|---|------|
| 1 | Buat Request + tambah Items + match ke Article |
| 2 | Auto tender / kelola Supplier Quotes + kirim email |
| 3 | Buat & ajukan Quotation Evaluation (QE) |
| 4 | Buat Buyer Quote, kirim, unggah buyer PO saat Accepted |
| 5 | Buat & ajukan Profit & Loss (PNL) |
| 6 | Buat Buyer Order → Issue Invoice → Record Payment |
| 7 | Buat Supplier Order → dual approval → kirim ke supplier |
| 8 | Goods Receive + approval dokumen |
| 9 | Fulfillment: Shipment / DO (barang) atau Acceptance Report (jasa) |
| 10 | Completion Report + approval finance |
| 11 | Credit Limit: ajukan kenaikan + dual approval finance |
| 12 | Undanggil Portal Users & approve Registrations |
| 13 | Activity Timeline & Request Notes |
| 14 | Import/Export master data (ringkas) |

Each playbook includes role callouts (CP / KA / Senior / Finance).

**Daily hub framing:** Request View with 8 tabs is the primary workspace; footer Information Flow widget explains next steps.

## Part 2 — Portal Buyer

### Overview

- Login, what buyers can/cannot see (no supplier cost, margin, or internal notes)
- Progress timeline (customer-facing milestones)
- Menus: Requests, Quotes, Invoices, Shipments, Activities/Notes

### Step-by-step

1. Lihat daftar & detail Request  
2. Terima atau tolak Buyer Quote  
3. Unduh PDF quote / invoice  
4. Lihat invoice & kirim bukti pembayaran (Pending)  
5. Lacak Shipment / Delivery Order  
6. Baca timeline & kirim catatan ke tim internal  
7. Registrasi dari public catalog (menunggu Approval → Registrations) — singkat  

## Part 3 — Portal Supplier

### Overview

- Login; data scoped to that supplier’s requests/quotes
- Menus: Requests (quote requests), My Articles

### Step-by-step

1. Buka quote request yang menunggu respons  
2. Isi harga, validity, payment terms; submit  
3. Perbarui harga di My Articles  
4. Kirim catatan (supplier-scoped) bila perlu  
5. Apa yang supplier lihat setelah quote dipilih / PO dikirim  

## Part 4 — Lampiran

- Glosarium istilah bisnis ↔ label UI  
- Tabel 13 Request stages → tab relevan  
- Matriks approval: dokumen → siapa → menu mana  
- FAQ (tab terkunci, quote expired, credit kurang, portal belum login)  
- Batasan UI yang diketahui untuk end-user (tanpa jargon teknis):
  - Invoice utama diterbitkan dari Buyer Order
  - Credit note belum ada di panel
  - Supplier invoice/payment tracking terbatas (gunakan PO + completion docs)

## Sources of truth

| Source | Use for |
|--------|---------|
| `FEATURES.md` §2 | End-to-end lifecycle, stages, gates |
| `FEATURES.md` §5 | Feature capabilities (verified) |
| `FEATURES.md` §6 | Who / when / why / how |
| `FEATURES.md` §5.17–5.18 | Buyer & Supplier portals |
| `resources/docs/USER_GUIDE.md` | Internal menu map reference |
| `docs/credit-limit-request-flow.md` | Credit limit steps (plain rewrite) |

Do not invent features marked limited/missing in `FEATURES.md` §6.8 / §11.

## Implementation outline (after plan)

1. Draft Part 0 + Part 4 scaffolding (glossary/stage/approval tables)  
2. Write Part 1 overviews + playbooks 1–7 (quote → order path)  
3. Write Part 1 playbooks 8–14 (fulfillment → close + admin ops)  
4. Write Part 2 (Buyer) and Part 3 (Supplier)  
5. Self-review against `FEATURES.md` for accuracy and language consistency  
6. Place file at `resources/docs/OPERATIONAL_MANUAL.md`  

No application code changes in v1.

## Success criteria

- A CP user can follow Request → QE → Buyer Quote → PNL → PO → GR → Fulfillment → Invoice without asking engineering  
- A buyer can accept a quote and submit payment proof from the portal using only this handbook  
- A supplier can respond to a quote request and update article prices using only this handbook  
- UI labels in the manual match Filament panel labels  
- `USER_GUIDE.md` and PDF download behavior remain unchanged  

## Open follow-ups (explicitly deferred)

- Switch PDF download to `OPERATIONAL_MANUAL.md`  
- Retire or demote `USER_GUIDE.md`  
- Add screenshots in a later revision  
- In-app help pages in Documentation module  
