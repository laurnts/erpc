# Buyer Invoice, Payment & Credit Flow — Design

Date: 2026-07-06
Status: Draft (pending user review)

## Problem

The "Invoices" tab on the Request detail is misleading and the buyer-credit accounting is
incomplete.

1. **The tab is not backed by an invoice.** `BuyerOrdersRelationManager` sets
   `$title = 'Invoices'`, so the tab labelled "Invoices" actually lists `BuyerOrder`
   records (using `OrderStatus`), not `BuyerInvoice` records.

2. **Two disconnected models.** A fuller AR model already exists but is **dormant**:
   `BuyerInvoice` (with `total`, `amount_paid`, `issued_at`, `due_at`, `net_days`,
   `amount_outstanding`, `days_overdue`, and `InvoiceStatus` draft/sent/partial/paid/
   overdue/cancelled) and `BuyerPayment` (with `PaymentMethod`, attached to an invoice,
   updating its paid status via `updatePaymentStatus()`). **Nothing in the UI creates a
   `BuyerInvoice` or a `BuyerPayment`.** They are only read by dashboard widgets
   (`AwaitingPaymentWidget`, `MonthlyRevenueWidget`, `RequiresAttentionWidget`), the
   `CheckOverdueInvoicesJob`, and a payment-terms calculation in `ViewRequest`.

3. **Credit only ratchets down.** Buyer credit lives on `Company`
   (`credit_limit`, `available_credit`, `credit_used`, `credit_status`) as denormalized
   columns, adjusted transactionally with a `BuyerCreditUsageHistory` ledger.
   `BuyerOrder::confirm()` is the **only** place credit is drawn (`available_credit -=`,
   `credit_used +=`), and `BuyerOrder::cancel()` is the **only** place it is restored.
   Because there is no payment path and payments have no link to credit, a buyer's
   available credit only ever decreases as they order and never recovers when they pay.
   This is the core bug.

## Goals

- Sending an invoice issues a **real `BuyerInvoice`** with a due date (termin), not just an
  order email.
- Staff can **record a buyer payment** (direct or on credit/termin) against an invoice.
- **Recording a payment releases buyer credit**, so `available_credit` reflects real open
  exposure.
- Keep the existing **credit gate at order confirmation** — it is the correct risk control.

## Non-goals (v1)

- **Multi-milestone termin** (each stage its own due date / progress billing). v1 is **one
  due date per invoice, with partial payments allowed** against it. Installment-style
  behaviour is covered by recording multiple partial `BuyerPayment`s against a single
  invoice; separate scheduled due dates are a future extension.
- Reworking credit into a fully derived (non-denormalized) value. We keep the existing
  denormalized columns + ledger and adjust them incrementally, matching `confirm()` /
  `cancel()`.
- Supplier-side invoice/payment changes.

## The model

### Credit invariant

Conceptually:

```
available_credit = credit_limit − open_exposure
open_exposure    = confirmed-but-unpaid orders/invoices
```

Implementation keeps the existing **incremental** approach (like `confirm()`/`cancel()`):
each event posts a `BuyerCreditUsageHistory` entry and adjusts the denormalized columns
under a `lockForUpdate()` transaction. The invariant above is the reconciliation target,
not a runtime `SUM()` query.

### Lifecycle (buyer with $10,000 limit, $3,000 order)

| Step | Credit effect | Notes |
|---|---|---|
| **Confirm order** | reserve $3k → available $7k | Unchanged. The gate: blocks the order if over limit, before procurement. |
| **Send → issue invoice** | **no change** (still $7k) | Exposure moves order→invoice. Sets `issued_at`, `due_at = issued_at + net_days` (the termin). |
| **Record payment (direct)** | release paid amount → back toward $10k | Cash settled; no termin/overdue tracking. |
| **Record payment (credit/termin)** | stays reserved until paid, then release | Due date tracked; feeds overdue widgets until settled. |
| **Cancel order** | restore remaining reserved amount | Guarded so already-released amounts are not double-restored. |

To the earlier A/B question this is **(A) semantics — draw early, restore at payment — but
drawn at Confirm, not at invoice**, because Confirm is the only point early enough to protect
procurement.

### Direct vs credit / termin

Both are `BuyerPayment` records and both **release credit on payment**. The only difference:

- **Direct** — no termin; expected to settle immediately; excluded from overdue tracking.
- **Credit / termin** — carries the invoice `due_at`; unpaid past due → `OVERDUE`; drives
  the "awaiting payment" / overdue widgets.

The credit line behaves identically for both. "Direct" is simply a credit/termin invoice
paid right away.

## Detailed flow & changes

### 1. Confirm (unchanged)

`BuyerOrder::confirm()` keeps drawing credit and writing the `debit` history entry. No
change to the risk gate.

### 2. Send → issue invoice

Today the `send` action flips the order `DRAFT → SENT` and emails `BuyerOrderToBuyerMail`.
Change: on send (or a dedicated "Issue invoice" action), create a `BuyerInvoice` linked to
the order:

- `buyer_order_id`, `request_id`, `currency`, `total` (from the order total), `net_days`
  (from buyer/team default), `issued_at = now()`, `due_at = issued_at + net_days`,
  `status = SENT`.
- Copy order line items into `BuyerInvoiceItem`s.
- Email the invoice (existing mail/PDF path — `DownloadPdfAction` already renders
  `BuyerInvoice`).
- **No credit movement** — the reservation from Confirm already covers this amount.

Guard: one active invoice per order (re-issuing an already-invoiced order should resend,
not duplicate). Credit notes remain the mechanism for reversals (`createCreditNote()`
already exists).

### 3. Record payment (new)

New record action on the Invoices tab → creates a `BuyerPayment`:

- Fields: `amount`, `payment_method` (Bank Transfer / Cash / Check / LC / Other),
  `payment_date`, `reference_number`, optional `payment_proof` upload (collection already
  registered), `notes`.
- "Direct vs credit/termin" is expressed by whether the invoice was issued on terms
  (`due_at`); the payment itself just records how cash arrived.
- `BuyerPaymentObserver` already calls `buyerInvoice->updatePaymentStatus()` on
  created/updated/deleted/restored → invoice moves SENT → PARTIAL → PAID (or OVERDUE)
  automatically.

### 4. Credit release (new — the missing piece)

When a payment settles part or all of an invoice, release the corresponding buyer credit:

- Add a release step (invoked from the payment lifecycle, e.g. in `BuyerPaymentObserver`
  or a small `BuyerOrder`/`BuyerInvoice` method) that, under `lockForUpdate()`:
  `available_credit += amount`, `credit_used = max(0, credit_used − amount)`, and posts a
  `credit` `BuyerCreditUsageHistory` entry (`related_type` = payment/invoice).
- Release is **bounded by what remains reserved for this order/invoice** to prevent
  over-release from overpayment or duplicate events.

### 5. Avoid double-restore on cancel

`BuyerOrder` already has a `creditRestoreHandled` flag. Extend the reservation-tracking so
that:

- `cancel()` only restores the **still-reserved** remainder (order total minus amounts
  already released by payments), never the full total once payments have released some.
- A fully paid order/invoice has zero remaining reservation, so cancelling it (if allowed)
  moves no credit.

This requires tracking "amount released so far" per order (a column or derived from the
ledger filtered by `related_id`). Preference: derive from the ledger to avoid a new
denormalized field; confirm during planning.

## State machines

- **OrderStatus** (unchanged): `draft → sent → confirmed → …`. `canConfirm()` = DRAFT/SENT.
- **InvoiceStatus** (already implemented, now actually exercised):
  `DRAFT → SENT → {PARTIAL, PAID, OVERDUE, CANCELLED}`, transitions enforced by
  `canTransitionTo()`. `updatePaymentStatus()` already computes PARTIAL/PAID/OVERDUE from
  `amount_paid` vs `total` and `due_at`.

## UI changes (Invoices tab)

- `send` action → also issues the `BuyerInvoice` (or a new explicit "Issue invoice" action).
- New **"Record payment"** action (visible when an invoice exists and is not fully
  paid/cancelled) → form above.
- Show invoice-level info in the tab: status, `due_at` (termin), `amount_outstanding`,
  overdue badge — sourced from `BuyerInvoice`, not the order.
- Keep `resend`, `confirm`, `cancel`.

## Edge cases

- **No buyer email** — invoice still issued; email skipped with a warning (mirrors current
  `send`).
- **Partial payment** — invoice → PARTIAL; release proportional credit; remainder stays
  reserved.
- **Overpayment** — clamp credit release to remaining reservation; surface a warning.
- **Cancel after partial payment** — restore only the still-reserved remainder.
- **`credit_status` disabled on the buyer** — skip all credit math (as `confirm()` /
  `restoreCredit()` already do); invoices and payments still work.
- **Zero / negative total** — no credit movement (matches `confirm()`).
- **Credit note** — issued via existing `createCreditNote()`; treated as a reversal, out of
  scope for automated credit release in v1 (note explicitly).

## Testing plan (Pest, feature-level)

- Confirm draws credit; issuing an invoice does **not** change credit.
- Recording a full payment releases the full reservation → `available_credit` back to limit.
- Recording a partial payment → invoice PARTIAL, proportional credit released, remainder
  reserved.
- Overpayment clamps release; no negative `credit_used`, no credit above limit.
- Cancel after a partial payment restores only the remainder (no double-restore).
- `credit_status = false` buyer: invoice + payment succeed, no credit rows written.
- Overdue: unpaid past `due_at` → OVERDUE and appears in the awaiting/overdue widgets.
- Concurrency: two simultaneous payments don't over-release (lock test).

## Open question to confirm at review

- **Termin = single due date (v1 assumption) vs staged installments with separate due
  dates.** v1 assumes single due date + partial payments. Confirm this is acceptable, or
  installments move in scope.
