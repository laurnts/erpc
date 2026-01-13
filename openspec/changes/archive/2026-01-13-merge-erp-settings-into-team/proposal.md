# Proposal: Merge ERP Settings into Team Settings

## Summary

Move ERP configuration from a standalone global settings page (`ErpSettingsPage`) into the existing Team Settings page (`EditTeam`). This change makes ERP settings team-scoped rather than global, aligning with the multi-tenant architecture.

## Motivation

1. **Multi-tenancy alignment**: Current `ErpSettings` uses Spatie Settings which stores values globally. In a multi-tenant app, each team should have its own ERP configuration (currency, tax rates, document prefixes, etc.)

2. **UX consolidation**: Users currently navigate to a separate "ERP Settings" page. Moving settings into the Team Settings page provides a single location for all team configuration.

3. **Data isolation**: Different teams may operate in different currencies, tax jurisdictions, or require different document numbering schemes.

## Current State

- `ErpSettings` class uses Spatie Laravel Settings (global storage)
- `ErpSettingsPage` is a standalone Filament page at `/erp-settings`
- Settings include: default currency, tax percent, quote validity, payment terms, company info, document number prefixes
- 19 files reference `ErpSettings` (observers, models, services, tests)

## Proposed Changes

### 1. Data Model Change
- Add `erp_settings` JSON column to `teams` table
- Create `TeamErpSettings` value object/DTO to type the JSON structure
- Add accessor/mutator on `Team` model for type-safe access

### 2. UI Change
- Create `UpdateTeamErpSettings` Livewire component (similar to `UpdateTeamName`)
- Add component to `EditTeam` page in a new "ERP Configuration" section
- Remove standalone `ErpSettingsPage`

### 3. Code Migration
- Update all 19 files using `ErpSettings` to read from current team's settings
- Provide sensible defaults when team settings are not configured
- Update tests to use team-scoped settings

## Impact Analysis

| Area | Files Affected | Risk |
|------|---------------|------|
| Observers | 10 files | Medium - Must get team context correctly |
| Models | 4 files | Low - Simple accessor change |
| Services | 1 file | Low |
| Tests | 2 files | Low - Test setup changes |
| Filament Pages | 1 file (removed) | Low |

## Out of Scope

- Migration of existing global settings to team settings (new installs start fresh)
- Settings inheritance between teams
- Settings templates/presets

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Breaking existing deployments | Provide defaults when no team settings exist |
| Team context unavailable in some code paths | Audit all usage sites; fail gracefully |
| Performance (JSON column queries) | Settings accessed via Team model which is typically loaded |

## Success Criteria

1. ERP settings appear in Team Settings page
2. Each team can have independent ERP configuration
3. All existing functionality works with team-scoped settings
4. Standalone ERP Settings page is removed
5. All tests pass
