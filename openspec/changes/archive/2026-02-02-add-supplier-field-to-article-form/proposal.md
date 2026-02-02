# Change: Add Supplier Field to Article Form

## Why

Currently, suppliers can only be assigned to articles via the Article → Suppliers relation manager tab. This requires navigating away from the article creation form and adds friction to the workflow. Users want to assign suppliers directly when creating or editing an article, similar to how categories/tags are assigned with an inline create button (+).

## What Changes

### Article Form Enhancement
- **ADDED** `suppliers` Select field to Article form schema
- **ADDED** Inline supplier creation capability (via + button)
- **MODIFIED** Article form to support supplier assignment in both modal and main form contexts
- **MODIFIED** Request Item creation flow to sync suppliers when creating articles inline

### User Experience
- Users can now assign suppliers directly in the Article form
- Users can create new suppliers inline without leaving the Article form
- Supplier field follows the same pattern as Categories field (multiple select, searchable, inline create)

## Impact

### Affected Files
- `app/Filament/Resources/ArticleResource.php` - Add suppliers field to form schema
- `app/Filament/Resources/RequestResource/RelationManagers/ItemsRelationManager.php` - Sync suppliers when creating article in modal

### No Breaking Changes
- Existing supplier assignment via relation manager remains functional
- All existing article-supplier relationships preserved
- Backward compatible with existing code

## Key Design Decisions

1. **Follow Category Pattern**: Supplier field mirrors the tags/categories field implementation for consistency
2. **Support Modal Context**: Field works in both main form and inline create modal (forModal parameter)
3. **Inline Create**: Uses SupplierResource form schema for consistency with other inline creates
4. **Multiple Selection**: Supports assigning multiple suppliers to an article (many-to-many relationship)
5. **Team Scoping**: Only shows suppliers from current team, filtered by is_supplier=true

## Success Criteria

1. Supplier field appears in Article form after Categories field
2. Users can select existing suppliers from dropdown
3. Users can create new suppliers inline via + button
4. Supplier assignments persist when creating/editing articles
5. Works correctly in both main Article form and inline create modal (Request Item flow)
6. Field is searchable and preloads options for better UX
