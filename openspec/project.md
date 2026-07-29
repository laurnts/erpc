# Project Context

## Purpose
ERPC is a deal lifecycle platform for back-to-back B2B trading — quote-to-cash on the
customer side, source-to-pay on the supplier side, joined in the middle by margin and
profit-and-loss tracking per deal. In one line: an ERP for a stockless trading
intermediary.

The system of record is the **deal** (a `Request`), not a product catalog and not an
order. Every customer-facing document has a supplier-facing mirror, and the business
value is the spread between them:

| Demand side (customer) | | Supply side (supplier) |
|---|---|---|
| Request | → sourcing → | Supplier quote request |
| Buyer Quote | ← evaluation ← | Supplier Quote |
| Buyer Order | back-to-back | Supplier Order |
| Buyer Invoice | | Supplier Invoice |
| Buyer Payment | deal P&L | Supplier Payment |

Forked from Relaticle CRM; the CRM base (companies, people, custom fields, teams)
remains underneath and is legacy surface, not the product.

## Tech Stack
- **Backend:** PHP 8.4, Laravel 12, Filament 5
- **Frontend:** Livewire 4, Tailwind CSS 4, Blade, Vite 7
- **Database:** PostgreSQL 15+
- **Queue/Cache:** Redis (Laravel Horizon for queue management)
- **AI Integration:** Prism PHP for AI capabilities
- **Media:** Spatie Media Library
- **Error Tracking:** Sentry
- **Authentication:** Laravel Jetstream, Sanctum, Socialite (SSO)

## Project Conventions

### Code Style
- **Formatter:** Laravel Pint with `laravel` preset
- **Strict Types:** All files must declare `strict_types=1`
- **Final Classes:** All classes must be `final` by default (with limited exceptions for base classes)
- **Strict Comparison:** Use `===` and `!==` exclusively
- **Refactoring:** Rector with Laravel sets, dead code removal, type declarations, privatization, early returns
- **Static Analysis:** PHPStan level 7 with Larastan

### Architecture Patterns
- **Modular Structure:** App modules in `app-modules/` (SystemAdmin, Documentation, OnboardSeed) with isolated namespaces
- **Module Independence:** Main app must not depend on SystemAdmin module; modules should minimize dependencies on main app
- **Readonly Classes:** Prefer readonly classes where state mutation is not required
- **Avoid Inheritance:** Prefer composition; classes should extend nothing except framework-required base classes
- **No Abstract Classes:** Avoid abstract classes except for designated base classes (BaseLivewireComponent, BaseImporter, BaseExporter)
- **Actions Pattern:** Business logic in `app/Actions/`
- **Data Objects:** Use Spatie Laravel Data for DTOs in `app/Data/`
- **Services:** Encapsulate external integrations in `app/Services/`

### Testing Strategy
- **Framework:** Pest 4 with Laravel and Livewire plugins
- **Test Types:** Feature tests, Unit tests, Architecture tests
- **Coverage Minimum:** 80% code coverage required
- **Type Coverage:** 99.9% minimum type coverage
- **Architecture Tests:** Enforce strict types, final classes, readonly classes, module boundaries
- **Parallel Execution:** Tests run in parallel for speed
- **CI Configuration:** Separate `phpunit.ci.xml` for CI environment

### Git Workflow
- **Main Branch:** `main`
- **CI/CD:** GitHub Actions (tests workflow)
- **Contributions:** Via Pull Requests
- **Pre-commit:** Git hooks in `.githooks/`

## Domain Context
- **Core entity:** `Request` — the deal. Everything else hangs off it.
- **Demand side:** `Request` → `RequestItem` → `BuyerQuote` → `BuyerOrder` →
  `BuyerInvoice` → `BuyerPayment`
- **Supply side:** `SupplierQuote` (collected per request) → `QuotationEvaluation`
  (bid comparison, sell-price construction) → `SupplierOrder` → `SupplierInvoice` →
  `SupplierPayment`
- **Fulfillment:** `Shipment`, `GoodsReceiveBatch`, `AcceptanceReport`; items within
  one deal may be fulfilled through different routes (mixed deals)
- **Economics:** `ProfitAndLoss` per deal — operational deal economics, not
  bookkeeping. Statutory accounting is deliberately out of scope and lives in
  dedicated software fed by exports.
- **Working capital:** buyer credit limits with approval workflows
  (`BuyerCreditLimitRequest`), `BuyerCreditUsageHistory` ledger, prepayment/balance
  invoice splitting, multi-currency with `ExchangeRate`.
- **Multi-Tenancy:** team-based isolation (`HasTeam`, `HasCreator` traits) with
  memberships and invitations.
- **Portals:** three panels — internal team (`app`), buyer portal, supplier portal.
  Tailored counterparty experience is a deliberate competitive surface.
- **CRM base (legacy):** Companies, People, Opportunities, Tasks, Notes, custom
  fields. Companies are dual-purpose: a company is a buyer, a supplier, or both.

## Important Constraints
- **License:** AGPL-3.0 (copyleft, derivative works must be open source)
- **PHP Version:** Requires PHP 8.4+ (uses latest language features)
- **Database:** PostgreSQL only (no MySQL/SQLite support for production)
- **Privacy:** Self-hosted, complete data ownership model
- **Performance:** Queue-based processing for heavy operations (Horizon)

## External Dependencies
- **Filament Admin:** Primary admin panel and resource management
- **Spatie Packages:** Media library, settings, data, prefixed IDs, markdown, login link, sitemap
- **Mailcoach:** Email marketing integration via Spatie SDK
- **Postmark:** Transactional email delivery
- **Sentry:** Error tracking and performance monitoring
- **Favicon Fetcher:** Company favicon retrieval
- **Shiki:** Code syntax highlighting
