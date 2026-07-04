# Executive Summary

## What This System Is

A **deal lifecycle platform for back-to-back B2B trading** — quote-to-cash on the customer side, source-to-pay on the supplier side, joined in the middle by margin and profit-and-loss tracking per deal.

In one line: **an ERP for a stockless trading intermediary.**

The system of record is the *deal*, not the product catalog and not the order. Every customer-facing document has a supplier-facing mirror, and the business value is captured in the spread between them:

| Demand side (customer) | | Supply side (supplier) |
|---|---|---|
| Request | → sourcing → | Supplier RFQ |
| Buyer Quote | ← evaluation ← | Supplier Quote |
| Buyer Order | back-to-back | Supplier Order |
| Buyer Invoice | | Supplier Invoice |
| Buyer Payment | deal P&L | Supplier Payment |

## The Business Model

A trading intermediary stacks three margins on every deal, and the domain model reflects all three:

1. **Sourcing margin** — win a customer request, run competitive supplier RFQs, and construct the sell price from evaluated supplier bids (`QuotationEvaluation`, sell-based margin convention).
2. **Fulfillment orchestration** — shipments, goods receipt, acceptance reports, and mixed deals where items within one deal are fulfilled through different routes.
3. **Working-capital management** — credit limits with approval workflows, prepayment/balance invoice splitting, payment terms on both sides, multi-currency with FX rates, and the float between buyer and supplier payment timing.

The third pillar is currently defensive (risk management on receivables). It is also the door to embedded trade finance: the moment credit extension carries a financing fee, it becomes a revenue line rather than a cost of doing business.

## Why Not Ecommerce Platforms (e.g. Magento/Adobe Commerce B2B)

Magento B2B offers negotiable quotes, company accounts, and credit balances — but the fit fails structurally, not on features:

- **Its quote is a discount on a catalog; ours is price discovery.** A Magento negotiable quote starts from a cart of posted prices. In this business the sell price often does not exist until supplier bids have been collected and evaluated.
- **It has no supply side.** No supplier entity, no RFQ, no purchase order *to* a supplier, no supplier invoice or payment, no goods receipt. Half the domain model has zero counterpart — Magento would be a login screen in front of a fully custom ERP.
- **No deal-level economics.** Magento knows order revenue and a static cost attribute; it cannot express per-deal margin built from an actual supplier quote in a different currency with different payment terms.
- **Wrong funnel.** Deals here start as CRM requests with stages, activities, and contacts — not at a product page.

*Heuristic: a platform fits when its central table matches your unit of value. Magento's is `catalog_product` + `sales_order`; ours is the deal.*

## Why Not a Generic ERP (e.g. Odoo)

Odoo could operate this business — it has sales, purchase, invoicing, CRM, and RFQ comparison. The trade-offs argue against it:

- **Module-centric, not deal-centric.** In Odoo, sale orders and purchase orders are independent documents in separate modules, stitched together by procurement routes and analytic accounts. Deal P&L is a reporting overlay, not a first-class object. Mixed deals — core to this business — would live in Odoo's periphery.
- **The differentiated 30% is custom either way.** Quotation evaluation, sell-based margin conventions, credit-limit approval workflows, prepayment/balance splitting, acceptance reports, RFQ terminal events — none are stock Odoo. That development would happen inside an inheritance-heavy framework with a notorious major-version upgrade treadmill, plus per-user Enterprise licensing.
- **Portals are product surface.** Three tailored experiences (internal team, customer portal, supplier RFQ portal) are a competitive moat for an intermediary. Odoo's generic portal skin resists this.
- **The build-vs-buy calculus has shifted.** Laravel + Filament + a forked CRM skeleton collapse the generic 70% (admin CRUD, auth, teams) to near-zero cost. Effort concentrates on the differentiated 30% — the part that *is* the business.

**Where bought software still wins:** statutory double-entry accounting, warehousing/inventory valuation, HR. Deliberately out of scope — the in-app `ProfitAndLoss` is operational deal economics, not bookkeeping. Accounting stays in dedicated software fed by exports; that boundary is intentional.

## Positioning

- **Not procurement software** — procurement here is a production process feeding the sell side, not the end goal.
- **Not ecommerce** — no cart, no checkout, no posted prices; the transactional grammar is negotiated quote-to-order.
- **Not fintech (yet)** — trade-credit operations exist but as risk management, not as a financial product.
- **Honest competitor set:** Odoo, SAP Business One, and trading-house vertical ERPs — beaten here on deal-centricity, counterparty experience, and speed of evolution.

The strategic bet: a stockless, deal-centric intermediary never needs most of a generic ERP's weight, and encoding the firm's own quote-turnaround speed, margin discipline, and credit control — rather than a generic ERP's median process — is the durable advantage.
