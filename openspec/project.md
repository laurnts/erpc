# Project Context

## Purpose
Relaticle is a next-generation open-source CRM (Customer Relationship Management) platform. It's designed for Laravel developers, agencies, and SMBs who need a modern, self-hosted CRM with unlimited customization capabilities. Key features include multi-team workspaces, no-code custom fields, and complete data ownership through self-hosting.

## Tech Stack
- **Backend:** PHP 8.4, Laravel 12, Filament 4
- **Frontend:** Livewire 3, Tailwind CSS 4, Blade, Vite 7
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
- **Core Entities:** Companies, People (contacts), Opportunities (deals), Tasks, Notes
- **Multi-Tenancy:** Team-based isolation with memberships and invitations
- **Custom Fields:** Extensible custom fields system for all entities (no-code customization)
- **AI Features:** AI-powered summaries for entities
- **Import/Export:** CSV import/export capabilities via Filament
- **Social Auth:** OAuth via user social accounts

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
