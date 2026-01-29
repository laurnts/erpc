# Design: Add Central Purchasing Team Role

## Context
The system currently supports two team member roles: Administrator and Editor. A new requirement is to add a "Central Purchasing" role that has the same permissions as Editor but requires an additional sub-role selection. The sub-roles (Key Account, Dept. Head of Sales, Deputy Director, Director) already exist as a `CentralPurchasingRole` enum used elsewhere in the system (for People model).

## Goals
- Add Central Purchasing as a third team member role option
- Require sub-role selection when Central Purchasing is selected
- Maintain consistency with existing role management patterns
- Reuse existing `CentralPurchasingRole` enum
- Ensure proper validation and data integrity

## Non-Goals
- Changing permissions for Central Purchasing role (uses same as Editor)
- Modifying the CentralPurchasingRole enum itself
- Adding new sub-role options beyond existing enum values

## Decisions

### Decision: Store sub-role in team_user pivot table
**Rationale**: The `team_user` table already stores the `role` column. Adding `central_purchasing_role` as a nullable column in the same table maintains data locality and avoids creating a separate relationship table. The column is nullable because it only applies when role is `central_purchasing`.

**Alternatives considered**:
- Separate `team_user_roles` table: Over-engineered for a single additional field
- JSON column: Less queryable and harder to maintain referential integrity

### Decision: Use existing CentralPurchasingRole enum
**Rationale**: The enum already exists and contains the exact four values needed (KEY_ACCOUNT, DEPT_HEAD_SALES, DEPUTY_DIRECTOR, DIRECTOR). Reusing it maintains consistency across the codebase.

**Alternatives considered**:
- Create new enum: Would duplicate values and create maintenance burden

### Decision: Conditional Select field in UI
**Rationale**: The sub-role selection should only appear when Central Purchasing is selected, reducing UI clutter and making the form more intuitive. This follows common UX patterns for conditional fields.

**Alternatives considered**:
- Always show sub-role field: Would confuse users when role is not Central Purchasing
- Separate form step: Adds unnecessary complexity

### Decision: Validate sub-role requirement
**Rationale**: When role is `central_purchasing`, the `central_purchasing_role` must be provided. This ensures data integrity and prevents incomplete role assignments.

**Implementation**: Validation rules will check that `central_purchasing_role` is required when `role === 'central_purchasing'` and nullable otherwise.

## Database Schema

### Migration: Add central_purchasing_role to team_user table
```php
Schema::table('team_user', function (Blueprint $table) {
    $table->string('central_purchasing_role')->nullable()->after('role');
});
```

**Note**: Using string instead of enum type for database compatibility. The enum casting will be handled at the model level.

## Risks / Trade-offs

### Risk: Data inconsistency if role changes
**Mitigation**: When role is changed from `central_purchasing` to another role, the `central_purchasing_role` should be set to null. When changing to `central_purchasing`, validation ensures sub-role is provided.

### Risk: Existing Central Purchasing users
**Mitigation**: Column is nullable, so existing records are not affected. Migration is non-breaking.

### Trade-off: Nullable column vs separate table
**Decision**: Nullable column is simpler and sufficient for this use case. If more role-specific attributes are needed in the future, consider refactoring to a more flexible structure.

## Migration Plan

1. Create migration to add `central_purchasing_role` column
2. Run migration (non-breaking, column is nullable)
3. Deploy code changes
4. No data migration needed (new feature, no existing data to migrate)

## Open Questions
- Should we display the sub-role in the team members table, or only in the detail view?
  - **Decision**: Display in detail view (ViewMember page). Table can show just "Central Purchasing" badge. Can be enhanced later if needed.
