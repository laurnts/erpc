# Design: Unit of Measure Management

## Context
The ERP system currently uses manual text input for units of measure across multiple forms. This proposal introduces a centralized Unit of Measure management system similar to the existing Tax Code management pattern.

## Goals / Non-Goals

### Goals
- Centralized unit management via admin UI
- Consistent unit selection across all forms
- Team-scoped units (each team has their own unit list)
- Migration of existing string values to UnitOfMeasure records
- Maintain backward compatibility during transition

### Non-Goals
- Unit conversion calculations (kg to lbs, etc.)
- Historical unit tracking (units are immutable once created)
- Unit categories or hierarchies
- Multi-language unit labels (single label per unit)

## Decisions

### Decision: Database Schema
**What**: Create `unit_of_measures` table similar to `tax_codes` structure.

**Why**: 
- Follows existing pattern (TaxCode) that teams are familiar with
- Team-scoped for multi-tenancy
- Simple structure: code (unique identifier), label (display name), is_active (enable/disable)

**Schema**:
```sql
unit_of_measures:
  - id (bigint, primary key)
  - team_id (foreign key, cascade delete)
  - creator_id (foreign key, nullable)
  - code (string, 50 chars, unique per team) - e.g., "pcs", "kg", "m"
  - label (string, 255 chars) - e.g., "Pieces", "Kilograms", "Meters"
  - is_active (boolean, default true)
  - is_default (boolean, default false) - only one default per team
  - sort_order (integer, default 0)
  - timestamps
  - Indexes: (team_id, code) unique, (team_id, is_active), (team_id, is_default)
```

**Alternatives considered**:
- Global units (no team scoping) - Rejected: Teams may need custom units
- Using enum only - Rejected: No admin UI, hard to maintain
- JSONB array on Team model - Rejected: Harder to query, no relationships

### Decision: Migration Strategy
**What**: Two-phase migration:
1. Create `unit_of_measures` table and seed default units
2. Migrate existing string values to foreign keys

**Why**:
- Allows gradual rollout
- Preserves existing data
- Can rollback if needed

**Migration Steps**:
1. Create `unit_of_measures` table
2. Seed default units for all teams (pcs, kg, mt, set, box, roll, pair, l, m)
3. Create `unit_of_measure_id` columns (nullable initially)
4. Migrate existing string values to foreign keys by matching code
5. Set default `unit_of_measure_id` for records with null values
6. Make `unit_of_measure_id` non-nullable
7. Drop old `unit` string columns (in separate migration after verification)

**Alternatives considered**:
- Single migration - Rejected: Too risky, harder to debug
- Keep both fields - Rejected: Data duplication, confusion

### Decision: Form Field Updates
**What**: Replace all `TextInput::make('unit')` with `Select::make('unit_of_measure_id')` using relationship or options.

**Why**:
- Consistent UX across all forms
- Prevents typos and invalid entries
- Shows team-specific units only

**Implementation Pattern**:
```php
Select::make('unit_of_measure_id')
    ->label('Unit of Measure')
    ->relationship('unitOfMeasure', 'label')
    ->searchable()
    ->preload()
    ->required()
    ->default(fn () => UnitOfMeasure::query()
        ->where('team_id', Filament::getTenant()?->id)
        ->where('code', 'pcs')
        ->value('id'))
```

**Alternatives considered**:
- Keep TextInput with autocomplete - Rejected: Still allows invalid entries
- Hybrid approach (text + validation) - Rejected: More complex, less user-friendly

### Decision: Model Relationships
**What**: Add `belongsTo(UnitOfMeasure::class)` relationship to all models with unit fields.

**Why**:
- Type-safe access to unit data
- Easy to query and filter
- Follows Laravel conventions

**Models affected**:
- Article
- RequestItem
- BuyerQuoteItem
- SupplierQuoteItem
- BuyerOrderItem
- SupplierOrderItem
- BuyerInvoiceItem
- SupplierInvoiceItem

### Decision: SafeUnitCast Update
**What**: Update `SafeUnitCast` to work with `UnitOfMeasure` model instead of enum.

**Why**:
- Maintains backward compatibility during migration
- Handles both old string values and new foreign keys gracefully
- Provides fallback to default unit

**Implementation**:
- Check if value is integer (foreign key) → load UnitOfMeasure
- Check if value is string → try to find UnitOfMeasure by code, fallback to 'pcs'
- Return UnitOfMeasure instance or default

**Alternatives considered**:
- Remove cast entirely - Rejected: Breaks existing code
- New cast class - Rejected: More files to maintain

### Decision: Default Units Seeding
**What**: Seed default units when team ERP is initialized or when UnitOfMeasureResource is first accessed.

**Why**:
- Teams need units immediately
- Common units should be available by default
- Can be customized per team

**Default units**:
- pcs (Pieces)
- kg (Kilograms)
- mt (Metric Tons)
- set (Sets)
- box (Boxes)
- roll (Rolls)
- pair (Pairs)
- l (Liters)
- m (Meters)

**Alternatives considered**:
- No seeding - Rejected: Empty state is poor UX
- Global defaults - Rejected: Teams may want different units

### Decision: Unit Enum Deprecation
**What**: Keep `Unit` enum temporarily for backward compatibility, mark as deprecated.

**Why**:
- Some code may still reference enum
- Gradual migration path
- Can remove in future cleanup
- SafeUnitCast still uses it for backward compatibility

**Alternatives considered**:
- Remove immediately - Rejected: Too risky
- Keep forever - Rejected: Technical debt

**Status**: Unit enum is still used by SafeUnitCast for backward compatibility. Will be deprecated in future cleanup phase.

### Decision: Unit Label Accessor
**What**: Added `getUnitLabelAttribute()` accessor to all item models.

**Why**:
- Consistent unit display across the application
- Handles both UnitOfMeasure relationships and legacy Unit enum
- Provides fallback to 'pcs' or '—' if no unit is set
- Used in PDF templates, view pages, and tables

**Implementation**:
- Checks for `unitOfMeasure` relationship first
- Falls back to Unit enum value if available
- Falls back to raw unit string
- Returns '—' if no unit is found

### Decision: Observer Pattern for Unit Sync
**What**: Use observers to sync `unit` field from `unit_of_measure_id` when creating/updating items.

**Why**:
- Maintains backward compatibility with existing code that expects `unit` field
- Ensures NOT NULL constraint is satisfied
- Uses `setRawAttributes` to bypass SafeUnitCast when setting unit

**Implementation**:
- BuyerQuoteItemObserver: Syncs unit on creating/updating
- SupplierQuoteItemObserver: Syncs unit on creating/updating
- UnitOfMeasureObserver: Handles team_id, creator_id, and default unit logic

### Decision: Creation Methods Update
**What**: Updated all item creation methods to properly copy `unit_of_measure_id` and set `unit` using `setRawAttributes`.

**Why**:
- Ensures unit field is always set when creating items from other items
- Prevents NOT NULL constraint violations
- Maintains data consistency

**Methods Updated**:
- BuyerOrderItem::createFromQuoteItem()
- SupplierOrder::createFromQuote()
- SupplierOrdersRelationManager item creation
- BuyerInvoiceItem::createFromOrderItem()

## Risks / Trade-offs

### Risk: Data Loss During Migration
**Mitigation**: 
- Two-phase migration with nullable foreign keys initially
- Comprehensive data mapping before dropping string columns
- Backup before migration
- Test migration on staging first

### Risk: Performance Impact
**Trade-off**: Additional JOIN queries when loading items with units.

**Mitigation**:
- Add indexes on `unit_of_measure_id` columns
- Use eager loading (`with('unitOfMeasure')`) in queries
- Consider caching frequently accessed units

### Risk: Breaking Existing Code
**Mitigation**:
- Update SafeUnitCast to handle both formats
- Comprehensive testing before deployment
- Feature flag for gradual rollout (if needed)

### Risk: User Confusion During Transition
**Mitigation**:
- Clear migration messaging
- Admin documentation
- Default units seeded automatically

## Migration Plan

### Phase 1: Create Infrastructure
1. Create migration for `unit_of_measures` table
2. Create UnitOfMeasure model
3. Create UnitOfMeasureResource
4. Seed default units for existing teams

### Phase 2: Update Models
1. Add `unit_of_measure_id` columns (nullable)
2. Add relationships to models
3. Update SafeUnitCast

### Phase 3: Migrate Data
1. Create migration script to map existing string values
2. Set `unit_of_measure_id` based on string `unit` values
3. Handle edge cases (invalid units, nulls)

### Phase 4: Update Forms
1. Update ArticleResource form
2. Update RequestResource relation managers
3. Update Quote/Order relation managers
4. Update Invoice forms (if applicable)

### Phase 5: Cleanup
1. Make `unit_of_measure_id` non-nullable
2. Drop old `unit` string columns
3. Remove deprecated Unit enum (future cleanup)

### Rollback Plan
- Keep old `unit` columns until Phase 5 is verified
- Can restore from backup if needed
- SafeUnitCast handles both formats during transition

## Open Questions
- Should units be deletable or only deactivatable? **Decision**: ✅ Deactivatable only (preserve data integrity)
- Should there be a "default unit" per team? **Decision**: ✅ Yes, implemented with `is_default` field (only one default per team)
- Should units have descriptions/help text? **Decision**: ✅ Not in v1, can add later if needed
- How to handle unit display in PDFs? **Decision**: ✅ Use `unit_label` accessor for consistent display
- How to handle unit in shipment items? **Decision**: ✅ ShipmentItem uses `getUnit()` method that calls `unit_label` accessor from order items

## Implementation Status

### ✅ Completed
- Database schema and migrations
- Model infrastructure (UnitOfMeasure, relationships, accessors)
- Admin resource (CRUD with view/edit/delete)
- Default units seeding
- Form updates (all forms converted to Select dropdowns)
- PDF template updates
- Observer pattern for unit sync
- Creation methods updated
- Shipment item unit handling
- Policies and permissions

### 🔄 In Progress
- Comprehensive testing
- Documentation updates

### 📋 Future Work
- Make `unit_of_measure_id` columns non-nullable
- Drop old `unit` string columns
- Deprecate Unit enum
- Add unit descriptions/help text (if needed)
