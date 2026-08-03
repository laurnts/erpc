# Operational Day-to-Day Manual Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create `resources/docs/OPERATIONAL_MANUAL.md` — one bilingual (Bahasa Indonesia + English UI labels) day-to-day handbook for internal staff, buyer portal users, and supplier portal users.

**Architecture:** Single Markdown handbook with five parts (Pengantar, Operasional Internal, Portal Buyer, Portal Supplier, Lampiran). Content is verified against `FEATURES.md` and Filament UI labels; no application code changes in v1.

**Tech Stack:** Markdown only · sources: `FEATURES.md`, `resources/docs/USER_GUIDE.md`, `docs/credit-limit-request-flow.md`, Filament resource titles

## Global Constraints

- Narrative language: Bahasa Indonesia; keep English UI labels exactly as on screen
- Audience: non-technical end users — no code, APIs, env vars, or developer jargon
- Deliverable path: `resources/docs/OPERATIONAL_MANUAL.md` only
- Do not modify `USER_GUIDE.md`, PDF download controller, or Settings link
- Do not invent features marked limited/missing in `FEATURES.md` §6.8 / §11
- Each playbook must open with **Siapa:** · **Kapan:** · **Hasil:**
- Use actual Filament tab titles: Requested Items · Supplier Quotes · Buyer Quotes · Supplier Orders · Goods Receive · Invoices · Fulfillment · Completion Report
- Role abbreviations: CP = Central Purchasing · KA = Key Account · Senior = Dept Head / Deputy Director / Director · Fin = Finance

---

## File Structure

| File | Responsibility |
|------|----------------|
| Create: `resources/docs/OPERATIONAL_MANUAL.md` | Full end-user operational handbook |
| Read-only: `docs/superpowers/specs/2026-08-03-operational-manual-design.md` | Approved scope |
| Read-only: `FEATURES.md` §§2, 5, 6 | Lifecycle, capabilities, who/when/why |
| Read-only: `resources/docs/USER_GUIDE.md` | Internal menu map |
| Read-only: `docs/credit-limit-request-flow.md` | Credit limit user steps (rewrite in plain BI) |

---

### Task 1: Scaffold handbook + Part 0 Pengantar + Part 4 Lampiran tables

**Files:**
- Create: `resources/docs/OPERATIONAL_MANUAL.md`
- Read: `FEATURES.md` (stages, gates, approval matrix §2 + §6.2)
- Read: `docs/superpowers/specs/2026-08-03-operational-manual-design.md`

**Interfaces:**
- Consumes: Design Parts 0 & 4 outline
- Produces: File skeleton with TOC; Part 0 complete; Part 4 glossary + stage table + approval matrix + FAQ stubs (FAQ answers filled in Task 5)

- [ ] **Step 1: Create file with title, TOC, and Part 0**

Write `resources/docs/OPERATIONAL_MANUAL.md` starting with:

```markdown
# Panduan Operasional Harian ERPC

> Buku panduan untuk pengguna non-teknis: tim internal, portal buyer, dan portal supplier.  
> Teks penjelasan dalam Bahasa Indonesia. Nama menu/tombol mengikuti tampilan sistem (bahasa Inggris).

## Daftar Isi

1. [Pengantar](#0-pengantar)
2. [Operasional Internal](#1-operasional-internal)
3. [Portal Buyer](#2-portal-buyer)
4. [Portal Supplier](#3-portal-supplier)
5. [Lampiran](#4-lampiran)

---

## 0. Pengantar

### Apa itu ERPC?

ERPC adalah sistem operasional pengadaan dan trading B2B. Tim menggunakan ERPC setiap hari untuk request, tender, quote, approval, order, pengiriman, dan invoice — sebelum atau bersamaan dengan pencatatan di sistem keuangan perusahaan.

### Siapa memakai apa?

| Anda adalah… | Masuk ke… | Bagian panduan |
|--------------|-----------|----------------|
| Staf internal (CP, Key Account, Finance, approver) | Aplikasi internal (panel tim) | Bagian 1 |
| Pengguna buyer | **Buyer Portal** | Bagian 2 |
| Pengguna supplier | **Supplier Portal** | Bagian 3 |

### Cara memakai panduan ini

1. Cari bagian sesuai peran Anda.
2. Baca ringkasan singkat, lalu ikuti langkah **Bagaimana caranya…**
3. Lihat **Lampiran** untuk istilah, stage, dan siapa yang menyetujui apa.
```

- [ ] **Step 2: Add Part 4 scaffolding — glossary, stages, approval matrix**

Append Part 4 with these tables (values from `FEATURES.md` §2 and §6.2):

**Glosarium (min. entries):** Request, Article, Supplier Quote, Quotation Evaluation (QE), Buyer Quote, Profit & Loss (PNL), Buyer Order / tab Invoices, Supplier Order (PO), Goods Receive, Fulfillment, Shipment / DO, Acceptance Report, Completion Report, Credit Limit Acceptances, Credit Limit Request

**Stage → tab:**

| Stage | Tab Request View |
|-------|------------------|
| Draft | Requested Items |
| Awaiting Supplier Response | Supplier Quotes |
| Preparing Buyer Quote / Awaiting Buyer Confirmation | Buyer Quotes |
| Preparing Supplier Order | Supplier Orders |
| Goods Receive | Goods Receive |
| Awaiting Shipment / Shipped / Delivered | Fulfillment |
| Invoiced / Paid / Completed | Invoices · Completion Report |
| Cancelled | — |

**Matriks approval:**

| Dokumen | Siapa | Menu |
|---------|-------|------|
| Quotation Evaluation | CP siapkan; Senior; KA via dokumen | Approval → Quotation Evaluations · Credit Limit Acceptances |
| Profit & Loss | CP siapkan; Senior + KA | Approval → Profit & Loss · Credit Limit Acceptances |
| Supplier Order | Senior (min. 2 orang berbeda) lalu kirim | Approval → Supplier Orders |
| Goods Receive | Approver sesuai kebijakan | Approval → Goods Receive |
| Completion / payment docs | Finance | Credit Limit Acceptances |
| Credit Limit Request | Finance (×2) | Approval → Credit Limit |
| Portal registration | CP / Admin | Approval → Registrations |

Leave FAQ as headings with placeholder bullets to fill in Task 5:

```markdown
### FAQ

#### Tab Request terkunci / tidak bisa dibuka
_(diisi Task 5)_

#### Buyer quote sudah expired
_(diisi Task 5)_

#### Credit buyer kurang
_(diisi Task 5)_

#### Belum bisa login portal
_(diisi Task 5)_

### Batasan yang perlu diketahui
- Invoice buyer biasanya diterbitkan dari **Buyer Order** (tab **Invoices** pada Request).
- Credit note belum tersedia di layar panel.
- Pelacakan invoice/pembayaran supplier masih terbatas — gunakan PO + dokumen Completion untuk saat ini.
```

- [ ] **Step 3: Verify scaffold**

Run: `test -f resources/docs/OPERATIONAL_MANUAL.md && wc -l resources/docs/OPERATIONAL_MANUAL.md`
Expected: file exists; roughly 80–150 lines

- [ ] **Step 4: Commit**

```bash
git add resources/docs/OPERATIONAL_MANUAL.md
git commit -m "$(cat <<'EOF'
docs: scaffold operational manual with intro and appendices

Add bilingual handbook skeleton covering Pengantar and Lampiran
tables for stages, glossary, and approval matrix.
EOF
)"
```

---

### Task 2: Part 1 overviews + playbooks 1–7 (inquiry → order)

**Files:**
- Modify: `resources/docs/OPERATIONAL_MANUAL.md`
- Read: `FEATURES.md` §§5.1–5.8, §6.1–6.4
- Read: `resources/docs/USER_GUIDE.md` §§1–5

**Interfaces:**
- Consumes: Scaffold from Task 1
- Produces: Part 1 overview blocks + playbooks 1–7 complete

- [ ] **Step 1: Write Part 1 overview blocks**

Insert under `## 1. Operasional Internal`:

1. **Ruang kerja harian** — Request View + 8 tabs + footer guidance widget  
2. **Dashboard** — Active Requests, Quotes Expiring, Awaiting Payment, Requires Attention, Pipeline by Stage, Monthly Revenue  
3. **Master Data** — Buyers, Suppliers, Articles, Categories, People, Projects (satu paragraf masing-masing)  
4. **Workflow** — Requests, Projects  
5. **Approval** — daftar menu + tujuan singkat  
6. **Finance** — daftar lintas-request (Buyer Quotes, Supplier Quotes, Buyer Orders, Supplier Orders, Credit Limits)  
7. **Settings operasional** — Currencies, Exchange Rates, Tax Codes, Unit Of Measures; SMTP/Email Templates = setup awal / tanya Administrator  

- [ ] **Step 2: Write playbooks 1–4**

For each playbook use this template:

```markdown
### Bagaimana caranya: [Judul tugas]

**Siapa:** …  
**Kapan:** …  
**Hasil:** …

1. …
2. …
```

**Playbook 1 — Buat Request + Items + match Article**  
Siapa: CP · Kapan: inquiry baru · Hasil: Request Draft dengan semua item matched  
Steps must include: Workflow → Requests → Create; pilih Buyer, type (goods/service), priority; buka tab **Requested Items**; tambah baris; match tiap baris ke **Article**; pastikan semua matched sebelum tender.

**Playbook 2 — Supplier Quotes / auto tender**  
Siapa: CP · Kapan: setelah items matched · Hasil: quote per supplier aktif untuk artikel terkait  
Steps: majukan stage / buka **Supplier Quotes** (auto-create); email supplier; isi/update harga, currency, payment terms, validity; status pending → selected/rejected.

**Playbook 3 — Quotation Evaluation (QE)**  
Siapa: CP siapkan; Senior & KA approve · Kapan: setelah bandingkan supplier quotes · Hasil: QE approved  
Steps: buat QE dari request; bandingkan harga/lead time/PKP/terms; PDF; unggah dokumen; approval via **Approval → Quotation Evaluations** / **Credit Limit Acceptances**.

**Playbook 4 — Buyer Quote**  
Siapa: CP, KA · Kapan: setelah QE (atau aturan bypass terpenuhi) · Hasil: quote terkirim; Accepted + buyer PO bila diterima  
Steps: tab **Buyer Quotes**; copy dari supplier quote atau buat manual; margin & payment terms (total 100%); kirim email/PDF; unggah buyer PO saat Accepted.

- [ ] **Step 3: Write playbooks 5–7**

**Playbook 5 — PNL**  
Siapa: CP siapkan; Senior + KA · Kapan: setelah buyer accept · Hasil: PNL approved sebelum belanja  
Steps: buat PNL; review cost/sell/margin per supplier; PDF; unggah; approve.

**Playbook 6 — Buyer Order → Issue Invoice → Record Payment**  
Siapa: CP (Fin pantau) · Kapan: quote Accepted · Hasil: order confirmed; invoice terbit; pembayaran tercatat  
Steps: tab **Invoices**; buat Buyer Order dari accepted quote; Issue Invoice; Record Payment (method, date, reference, proof); credit reserved/released sesuai pembayaran.

**Playbook 7 — Supplier Order dual approval → send**  
Siapa: CP buat; Senior ×2 approve · Kapan: setelah PNL & path order siap · Hasil: PO Approved lalu Sent ke supplier  
Steps: tab **Supplier Orders**; buat PO per supplier; Confirm; **Approval → Supplier Orders** (min. 2 approver berbeda); baru kemudian kirim email/PDF ke supplier; dokumen via Credit Limit Acceptances (KA).

- [ ] **Step 4: Spot-check UI labels**

Run: `rg -n "Requested Items|Supplier Quotes|Buyer Quotes|Supplier Orders|Goods Receive|Invoices|Fulfillment|Completion Report|Credit Limit Acceptances" resources/docs/OPERATIONAL_MANUAL.md | head -40`  
Expected: labels present; no invented tab name like "Purchases" unless also explained as alias

- [ ] **Step 5: Commit**

```bash
git add resources/docs/OPERATIONAL_MANUAL.md
git commit -m "$(cat <<'EOF'
docs: add internal ops overviews and quote-to-order playbooks

Cover Part 1 summaries and playbooks 1–7 from request through
supplier PO dual approval.
EOF
)"
```

---

### Task 3: Part 1 playbooks 8–14 (fulfillment → close + ops)

**Files:**
- Modify: `resources/docs/OPERATIONAL_MANUAL.md`
- Read: `FEATURES.md` §§5.9, 5.12–5.13, 5.19–5.21, §6.1–6.3, §6.7
- Read: `docs/credit-limit-request-flow.md` (user-facing steps only)

**Interfaces:**
- Consumes: Part 1 through playbook 7
- Produces: Playbooks 8–14 complete; Part 1 finished

- [ ] **Step 1: Write playbooks 8–10**

**Playbook 8 — Goods Receive**  
Siapa: CP / warehouse; Approver · Kapan: barang datang · Hasil: GR dokumen approved; Fulfillment terbuka (goods)  
Steps: tab **Goods Receive**; unggah batch per supplier order; **Approval → Goods Receive**.

**Playbook 9 — Fulfillment**  
Siapa: CP / logistics · Kapan: setelah GR approved (goods) · Hasil: DO terkirim atau Acceptance Report tersimpan  
Goods: tab **Fulfillment** → Shipments; set PIC dari People; generate DO PDF; email buyer.  
Services: **Acceptance Reports** di tab yang sama. Mixed: keduanya.

**Playbook 10 — Completion Report**  
Siapa: CP; Fin approve dokumen · Kapan: closing · Hasil: completion/payment docs approved  
Steps: tab **Completion Report**; unggah; Fin approve via Credit Limit Acceptances / payment document path.

- [ ] **Step 2: Write playbooks 11–14**

**Playbook 11 — Credit Limit increase**  
Siapa: pemohon + Fin ×2 · Kapan: limit tidak cukup · Hasil: `credit_limit` aktif naik setelah dual approval  
Steps plain-language from credit-limit flow: ajukan dari buyer/credit UI; **Approval → Credit Limit**; dua finance approver; limit aktif baru berubah setelah keduanya setuju.

**Playbook 12 — Portal Users & Registrations**  
Siapa: CP / KA / Admin · Kapan: onboarding eksternal · Hasil: user portal aktif atau registration ditolak  
Steps: Master Data → Buyers/Suppliers → **Portal Users** invite; **Approval → Registrations** untuk signup katalog.

**Playbook 13 — Activity Timeline & Request Notes**  
Siapa: CP, KA; buyer/supplier via portal · Kapan: kapan saja · Hasil: jejak aktivitas + catatan bertingkat  
Steps: scroll footer Activities; post note; staff pilih visibility (internal / to buyer / to supplier).

**Playbook 14 — Import/Export (ringkas)**  
Siapa: Admin, CP · Kapan: migrasi / laporan · Hasil: file CSV/Excel terunggah atau terunduh  
Steps: tombol Import/Export di list People, Buyers, Suppliers, Articles; export juga untuk QE, PNL, quotes, orders.

- [ ] **Step 3: Add goods vs service callout**

Short subsection under Part 1 (from `FEATURES.md` §6.7): goods → Shipments + GR; service → Acceptance Reports; mixed → both; approval pattern sama.

- [ ] **Step 4: Commit**

```bash
git add resources/docs/OPERATIONAL_MANUAL.md
git commit -m "$(cat <<'EOF'
docs: add fulfillment, closing, and admin ops playbooks

Complete Part 1 with goods receive through import/export and
goods-vs-service guidance.
EOF
)"
```

---

### Task 4: Parts 2 & 3 — Portal Buyer and Portal Supplier

**Files:**
- Modify: `resources/docs/OPERATIONAL_MANUAL.md`
- Read: `FEATURES.md` §§5.17–5.18, §5.21a, §6.5
- Verify labels: Buyer panel `Quotes`, `Invoices`, `Shipments`; Supplier `Requests`, `My Articles`

**Interfaces:**
- Consumes: Handbook Parts 0–1 + Lampiran tables
- Produces: Parts 2 and 3 complete

- [ ] **Step 1: Write Part 2 — Portal Buyer**

Overview:
- Login Buyer Portal
- Tidak melihat: harga supplier, margin, catatan internal
- Progress timeline (milestone customer-facing)
- Di dalam Request: Quotes, Invoices, Shipments, Activities/Notes

Playbooks (template Siapa/Kapan/Hasil):
1. Lihat daftar & detail Request  
2. Terima / tolak quote di **Quotes**  
3. Unduh PDF quote / invoice  
4. Kirim bukti bayar (status **Pending** sampai dikonfirmasi staf)  
5. Lacak **Shipments**  
6. Baca timeline & kirim catatan  
7. Registrasi katalog publik → menunggu **Approval → Registrations** (singkat)

Note: status invoice **Sent** ditampilkan sebagai **Received** untuk buyer.

- [ ] **Step 2: Write Part 3 — Portal Supplier**

Overview:
- Login Supplier Portal; data terbatas ke supplier tersebut
- Menu: **Requests**, **My Articles**

Playbooks:
1. Buka request/quote yang menunggu respons  
2. Isi harga, validity, payment terms; submit  
3. Update harga di **My Articles**  
4. Catatan supplier-scoped  
5. Setelah quote dipilih / PO dikirim — apa yang terlihat vs tidak (tanpa margin internal, dll.)

- [ ] **Step 3: Label check**

Run: `rg -n "Buyer Portal|Supplier Portal|My Articles|Quotes|Pending|Received" resources/docs/OPERATIONAL_MANUAL.md | head -30`  
Expected: portal sections present with correct labels

- [ ] **Step 4: Commit**

```bash
git add resources/docs/OPERATIONAL_MANUAL.md
git commit -m "$(cat <<'EOF'
docs: add buyer and supplier portal operational guides

Document day-to-day portal tasks for accepting quotes, payments,
shipments, supplier responses, and article pricing.
EOF
)"
```

---

### Task 5: Fill FAQ, self-review accuracy, final commit

**Files:**
- Modify: `resources/docs/OPERATIONAL_MANUAL.md`
- Read: `FEATURES.md` §§2 (gates), 6.8 (limits), design success criteria

**Interfaces:**
- Consumes: Full draft Parts 0–4
- Produces: Production-ready handbook meeting design success criteria

- [ ] **Step 1: Fill FAQ answers**

| FAQ | Answer must cover |
|-----|-------------------|
| Tab terkunci | Gates: QE approved; PNL approved; accepted buyer quote + PO upload; GR approved before Fulfillment (goods) |
| Quote expired | Reminder harian; perpanjang/revisi lewat tim internal; buyer lihat status expired |
| Credit kurang | Ajukan Credit Limit Request; dual Fin approval; order confirmation memakai credit |
| Portal belum login | Harus diundang Portal Users atau registration disetujui; cek email undangan |

- [ ] **Step 2: Accuracy pass against FEATURES.md**

Checklist (mark in commit message / notes):
- [ ] 8 Request tabs match Filament titles  
- [ ] Dual PO approval = min. 2 senior  
- [ ] Fulfillment = Shipments + Acceptance Reports  
- [ ] Buyer invoice from order; credit note not in UI  
- [ ] No sysadmin / API / env content  
- [ ] Bahasa Indonesia narrative + English labels  
- [ ] Every playbook has Siapa/Kapan/Hasil  

Run: `rg -n "TBD|TODO|FIXME|Purchases|lorem|xxx" resources/docs/OPERATIONAL_MANUAL.md`  
Expected: no matches (or only intentional glossary if any)

- [ ] **Step 3: Line-count / structure check**

Run: `rg -n "^## |^### Bagaimana caranya" resources/docs/OPERATIONAL_MANUAL.md`  
Expected: Parts 0–4 headers present; ≥14 internal playbooks + buyer/supplier playbooks

- [ ] **Step 4: Final commit**

```bash
git add resources/docs/OPERATIONAL_MANUAL.md
git commit -m "$(cat <<'EOF'
docs: finalize operational manual FAQ and accuracy pass

Complete FAQ answers and verify playbooks against product
features and Filament UI labels.
EOF
)"
```

- [ ] **Step 5: Confirm non-goals untouched**

Run: `git diff HEAD~5 -- resources/docs/USER_GUIDE.md app/Http/Controllers/UserGuideDownloadController.php || true`  
Expected: no changes to those files in this work (or empty diff)

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Part 0 Pengantar | Task 1 |
| Part 1 overviews | Task 2 |
| Playbooks 1–7 | Task 2 |
| Playbooks 8–14 + goods/service | Task 3 |
| Part 2 Buyer | Task 4 |
| Part 3 Supplier | Task 4 |
| Part 4 glossary/stages/approval/FAQ/limits | Tasks 1 + 5 |
| No USER_GUIDE/PDF changes | Task 5 Step 5 |
| BI + English labels | Global + all tasks |
| Success criteria CP/buyer/supplier flows | Tasks 2–4 playbooks |

## Placeholder scan

Plan contains no TBD implementation steps; FAQ placeholders are explicitly filled in Task 5 Step 1.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-03-operational-manual.md`.

**Two execution options:**

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  

**2. Inline Execution** — execute tasks in this session with checkpoints  

Which approach?
