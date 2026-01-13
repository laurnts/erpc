# Tasks: Merge ERP Settings into Team Settings

## Phase 1: Data Model

### 1.1 Database Migration
- [x] 1.1.1 Create migration to add `erp_settings` JSON column to `teams` table
- [x] 1.1.2 Set default value as empty JSON object `{}`

### 1.2 Value Object
- [x] 1.2.1 Create `App\Data\TeamErpSettings` Spatie Data DTO with all ERP settings properties
- [x] 1.2.2 Define defaults matching current `ErpSettings` defaults
- [x] 1.2.3 Add validation rules for each property

### 1.3 Team Model Integration
- [x] 1.3.1 Add `erp_settings` cast to `Team` model using `TeamErpSettings::class`
- [x] 1.3.2 Add `getErpSettings(): TeamErpSettings` accessor with defaults fallback
- [x] 1.3.3 Add `erp_settings` to `$fillable` array
- [x] 1.3.4 Write unit tests for Team ERP settings accessor

## Phase 2: Livewire Component

### 2.1 Create Component
- [x] 2.1.1 Create `App\Livewire\App\Teams\UpdateTeamErpSettings` Livewire component
- [x] 2.1.2 Add form with all ERP settings fields (matching current ErpSettingsPage)
- [x] 2.1.3 Group fields into sections: Company Info, Default Settings, Document Prefixes
- [x] 2.1.4 Add save action that updates team's `erp_settings` JSON
- [x] 2.1.5 Add success notification on save

### 2.2 Create Blade View
- [x] 2.2.1 Create `resources/views/livewire/teams/update-team-erp-settings.blade.php`
- [x] 2.2.2 Style consistently with other team settings components
- [x] 2.2.3 Use Filament form components for inputs

### 2.3 Integrate into EditTeam
- [x] 2.3.1 Add `UpdateTeamErpSettings` Livewire component to `EditTeam::form()`
- [x] 2.3.2 Position after team name, before team members section
- [x] 2.3.3 Write feature test for ERP settings in team page

## Phase 3: Code Migration

### 3.1 Update Observers (10 files)
- [x] 3.1.1 Update `BuyerObserver` to use team's ERP settings
- [x] 3.1.2 Update `SupplierObserver` to use team's ERP settings
- [x] 3.1.3 Update `RequestObserver` to use team's ERP settings
- [x] 3.1.4 Update `ProjectObserver` to use team's ERP settings
- [x] 3.1.5 Update `BuyerQuoteObserver` to use team's ERP settings
- [x] 3.1.6 Update `SupplierQuoteObserver` to use team's ERP settings
- [x] 3.1.7 Update `SupplierOrderObserver` to use team's ERP settings
- [x] 3.1.8 Update `ShipmentObserver` to use team's ERP settings
- [x] 3.1.9 Update `SupplierInvoiceObserver` to use team's ERP settings
- [x] 3.1.10 Update `SupplierPaymentObserver` to use team's ERP settings

### 3.2 Update Models (4 files)
- [x] 3.2.1 Update `BuyerQuote` model to use team's ERP settings
- [x] 3.2.2 Update `BuyerOrder` model to use team's ERP settings
- [x] 3.2.3 Update `BuyerInvoice` model to use team's ERP settings
- [x] 3.2.4 Update `BuyerPayment` model to use team's ERP settings

### 3.3 Update Services (1 file)
- [x] 3.3.1 Update `PdfGenerationService` to accept team or get from context

## Phase 4: Cleanup

### 4.1 Remove Old Code
- [x] 4.1.1 Delete `app/Filament/Pages/ErpSettingsPage.php`
- [x] 4.1.2 Delete `resources/views/filament/pages/erp-settings.blade.php`
- [x] 4.1.3 Delete `app/Settings/ErpSettings.php` (after confirming no usage)
- [x] 4.1.4 Delete ERP settings migrations from `database/settings/`
- [x] 4.1.5 Remove `erp` group from Spatie Settings if no longer needed

### 4.2 Update Tests
- [x] 4.2.1 Update `ErpSettingsTest` to test team-scoped settings
- [x] 4.2.2 Update `PdfGenerationTest` to use team settings
- [x] 4.2.3 Update any other tests that mock or use ErpSettings

### 4.3 Update Permissions
- [x] 4.3.1 Remove `view erp settings` and `update erp settings` permissions
- [x] 4.3.2 Add team-level permission check for ERP settings (team owner/admin only)

## Phase 5: Validation

### 5.1 Testing
- [x] 5.1.1 Run full test suite: `composer test`
- [x] 5.1.2 Run architecture tests: `composer test:arch`
- [x] 5.1.3 Run PHPStan: `composer test:types`
- [x] 5.1.4 Manual testing: Create team, configure ERP settings, verify functionality

### 5.2 Documentation
- [x] 5.2.1 Update any documentation referencing ERP Settings page

---

## Task Summary

| Phase | Tasks | Parallelizable |
|-------|-------|----------------|
| 1. Data Model | 8 | Some (1.2 after 1.1) |
| 2. Livewire Component | 8 | Some (2.2 with 2.1) |
| 3. Code Migration | 15 | Yes (all observers/models independent) |
| 4. Cleanup | 8 | After Phase 3 complete |
| 5. Validation | 5 | Sequential |
| **Total** | **44** | |

## Dependencies

```
Phase 1 (Data Model)
    ↓
Phase 2 (Livewire Component)  ←── can start 2.1 after 1.2
    ↓
Phase 3 (Code Migration)      ←── can start after 1.3
    ↓
Phase 4 (Cleanup)             ←── must wait for Phase 3
    ↓
Phase 5 (Validation)
```
