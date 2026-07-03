# Design: Item-Level Fulfillment

## Context

`request_type` currently gates five behaviors (hierarchy, acceptance reports, shipments, job progress, quotation evaluation) for a whole request. The capability-methods refactor centralized these rules on the enum; every call site now asks a capability question (`supportsItemHierarchy()`…) instead of an identity question (`isServiceRequest()`). That seam is what makes this change tractable: the questions stay the same, only the *subject* changes from request to item.

## Goals / Non-Goals

**Goals**
- One request can hold goods and service items simultaneously.
- Each item fulfills through its own channel; the request completes when all items are satisfied.
- No change to how totals, margins, or document numbering work.

**Non-Goals**
- No per-item pricing model changes.
- No changes to supplier/buyer quote document structure beyond gating existing hierarchy/job-progress features per item.
- No user-defined item types — the enum stays closed (two cases) because each case carries workflow behavior.

## Decisions

### D1. Type lives on `request_items.item_type`; requests have no type

Rejected alternative: keep `requests.request_type` as a "default for new items". It preserves a second source of truth and the exact request-level assumption this change removes. The item form's type toggle defaults to `goods` (most common), which covers the convenience without the concept.

### D2. `RequestType` enum renamed `ItemType`; capability methods move with it

The enum name must say what it classifies. DB values (`goods`, `services`) are unchanged, so the rename is code-only. Capability methods keep their exhaustive `match` arms — a future third type still forces every rule to be answered.

`Request` loses its capability passthroughs and instead exposes **presence** helpers derived from items:

```
hasGoodsItems(): bool      // gates shipments tab, QE availability
hasServiceItems(): bool    // gates acceptance reports tab
```

Presence checks use `items()->where('item_type', …)->exists()` (or the loaded collection when available) — never a cached column, to avoid invalidation bugs when items change type.

### D3. Child items always excluded from totals; hierarchy is a service-item feature

Today `filterForTotals(bool $hasItemHierarchy)` is gated per request. After this change, only service items can have children, and children are never priced independently — so the exclusion becomes unconditional: `filterForTotals()` drops any line whose request item is a child. The boolean parameter disappears. Goods items are unaffected (they have no children to exclude).

### D4. Completion = every item satisfied through its own channel

- Goods items: covered by received shipments (existing shipment-item quantity logic, scoped to goods items).
- Service items: covered when a main item appears on ≥1 acceptance report (existing pivot, scoped to service items).
- Stage matching validation: goods items and service *main* items require `article_id`; service child items are exempt (unchanged rule, re-keyed from request type to item type).

### D5. Quotation Evaluation scopes to goods items

QE remains a goods-only comparison instrument. Availability: request has ≥1 goods item. Content: goods items only. The `QuotationEvaluationForm` guard changes from request-type to goods-item-presence; item listings filter by type. Service-only requests behave exactly as today (no QE).

### D6. Migration is backfill-then-drop, with derivable rollback

1. Add `request_items.item_type` (string(20), default `'goods'`, indexed with `team_id`).
2. Backfill: every item takes its request's `request_type`.
3. Drop `requests.request_type` (and its index) in a **separate, later migration** so the deploy window keeps a rollback path.
4. `down()` for the drop-migration re-creates the column and sets each request's type to `services` iff *all* its items are service items, else `goods` — the conservative inverse (a request that was mixed never existed before this change).

### D7. Both fulfillment tabs coexist, item-filtered

`canViewForRecord()` on each relation manager switches from request capability to item presence. Item pickers inside shipment and acceptance-report forms filter to eligible items only. Neither tab hides the other.

## Risks / Trade-offs

- **Widest blast radius is the two quote relation managers** (~1,500 lines each) where hierarchy branches become per-item. Mitigation: the branches already call capability methods; the change is mechanical re-keying plus tests. Consider extracting shared quote-form logic opportunistically, not as part of this change.
- **Presence queries add N small `exists()` calls** on request view pages. Mitigation: `withExists()` eager annotation on the view page queries.
- **P&L and PDF blades** mix hierarchy display with type labels; each blade site must be re-checked against D3 rather than mass-replaced.
- **Portal UX**: customers now pick a type per item. Default `goods` keeps the common path one-click.

## Migration / Rollout

1. Archive `add-service-request-type` (deployed; lands its requirements in base specs).
2. Ship schema + backfill migration (column addition is non-breaking; code still reads request type).
3. Ship code cutover keyed to `item_type` (request selector removed the same release).
4. Ship column-drop migration one release later.
5. At apply time, extend this change's deltas with `REMOVED: Request Type Classification`, `REMOVED: Service Request Child Items` (superseded by item-level requirements) once archiving makes them referenceable.

## Open Questions

- None blocking. Job-progress payment terms currently render when the request is a service request; after this change they render when the quote covers ≥1 service item — confirmed acceptable since the field is per payment-term row, not per item.
