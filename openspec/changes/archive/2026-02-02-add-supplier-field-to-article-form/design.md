# Design: Add Supplier Field to Article Form

## Overview

Add a supplier selection field to the Article form that allows users to assign suppliers directly when creating or editing articles, with inline supplier creation capability.

## Current State

### Article Form Structure
- Article form has a `tags` field (Categories) with inline create capability
- Suppliers are currently only assignable via Article → Suppliers relation manager tab
- Article model has `suppliers()` relationship method already defined

### Existing Pattern (Categories/Tags)
```php
$tagsSelect = Select::make('tags')
    ->label('Categories')
    ->multiple()
    ->preload()
    ->searchable()
    ->createOptionForm(TagResource::getFormSchema())
    ->createOptionUsing(function (array $data): int {
        // Create tag and return ID
    });

if ($forModal) {
    $tagsSelect->options(fn (): array => /* query tags */);
} else {
    $tagsSelect->relationship('tags', 'name');
}
```

## Proposed Solution

### Supplier Field Implementation

Follow the exact same pattern as the tags field:

```php
$suppliersSelect = Select::make('suppliers')
    ->label('Suppliers')
    ->multiple()
    ->preload()
    ->searchable()
    ->createOptionForm(SupplierResource::getFormSchema(excludePeopleField: true))
    ->createOptionUsing(function (array $data): int {
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();
        
        /** @var Company $supplier */
        $supplier = Company::create([
            ...$data,
            'is_supplier' => true,
            'team_id' => $team->id,
            'creator_id' => auth()->id(),
        ]);
        
        return $supplier->id;
    });

if ($forModal) {
    $suppliersSelect->options(fn (): array => Company::query()
        ->where('team_id', Filament::getTenant()?->getKey())
        ->where('is_supplier', true)
        ->where('is_active', true)
        ->orderBy('name')
        ->get()
        ->mapWithKeys(fn (Company $supplier): array => [
            $supplier->getKey() => $supplier->name,
        ])
        ->toArray());
} else {
    $suppliersSelect->relationship('suppliers', 'name')
        ->modifyQueryUsing(fn ($query) => $query->where('is_supplier', true));
}
```

### Form Schema Order

Place the suppliers field after the categories field:

```php
return [
    TextInput::make('name'),
    TextInput::make('sku'),
    TextInput::make('unit'),
    $taxCodeSelect,
    $tagsSelect,        // Categories
    $suppliersSelect,   // NEW: Suppliers
    Textarea::make('description'),
    // ... rest of fields
];
```

### Modal Context Handling

When creating articles inline from Request Item form, sync suppliers:

```php
->createOptionUsing(function (array $data) use ($request): int {
    /** @var Article $article */
    $article = Article::create([...]);
    
    // Sync tags if provided
    if (! empty($data['tags'])) {
        $article->tags()->sync($data['tags']);
    }
    
    // NEW: Sync suppliers if provided
    if (! empty($data['suppliers'])) {
        $article->suppliers()->sync($data['suppliers']);
    }
    
    return $article->getKey();
})
```

## Design Decisions

### 1. Follow Existing Pattern
- Use the same structure as tags field for consistency
- Users already understand this pattern
- Reduces cognitive load

### 2. Inline Create with SupplierResource
- Reuse `SupplierResource::getFormSchema()` for consistency
- Exclude People field to prevent circular references (similar to other inline creates)
- Ensures supplier creation form matches main supplier form

### 3. Team and Active Filtering
- Only show suppliers from current team
- Only show active suppliers (`is_active = true`)
- Filter by `is_supplier = true` to ensure only suppliers appear

### 4. Multiple Selection
- Support assigning multiple suppliers (many-to-many relationship)
- Matches the categories field behavior
- Allows articles to have multiple suppliers

### 5. Modal Context Support
- Handle both main form and modal contexts
- Use `options()` for modal to avoid model context issues
- Use `relationship()` for main form for better performance

## Implementation Notes

### File Changes

1. **ArticleResource.php**
   - Add suppliers Select field to `getFormSchema()` method
   - Position after tags field
   - Handle both `forModal` and normal form contexts

2. **ItemsRelationManager.php**
   - Update `createOptionUsing` callback to sync suppliers
   - Ensure suppliers are synced when creating article inline

### Testing Considerations

- Test supplier assignment in main Article form
- Test supplier assignment in modal (Request Item flow)
- Test inline supplier creation
- Test with multiple suppliers
- Test search functionality
- Verify team scoping works correctly
- Verify only active suppliers appear

## Alternatives Considered

### Alternative 1: Relation Manager Only
- **Rejected**: Adds friction, requires navigation away from form

### Alternative 2: Separate Supplier Assignment Step
- **Rejected**: Unnecessary complexity, breaks workflow

### Alternative 3: Single Supplier Selection
- **Rejected**: Articles can have multiple suppliers, many-to-many relationship exists

## Success Metrics

- Users can assign suppliers directly in Article form
- Inline supplier creation works seamlessly
- No regression in existing functionality
- Consistent UX with categories field
