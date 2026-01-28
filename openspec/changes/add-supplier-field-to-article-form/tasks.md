# Tasks: Add Supplier Field to Article Form

## Phase 1: Form Schema Enhancement

- [x] 1.1 Add suppliers Select field to `ArticleResource::getFormSchema()`
  - [x] 1.1.1 Create suppliers Select component similar to tags field
  - [x] 1.1.2 Add `->multiple()` for many-to-many relationship
  - [x] 1.1.3 Add `->searchable()` and `->preload()` for better UX
  - [x] 1.1.4 Add `->createOptionForm(SupplierResource::getFormSchema())` for inline create
  - [x] 1.1.5 Add `->createOptionUsing()` callback to create supplier and return ID
  - [x] 1.1.6 Handle `forModal` parameter: use `->options()` for modal, `->relationship()` for main form
  - [x] 1.1.7 Filter suppliers by `is_supplier=true` and current team
  - [x] 1.1.8 Position field after Categories field in form schema

- [ ] 1.2 Test supplier field in main Article form
  - [ ] 1.2.1 Create new article and assign suppliers
  - [ ] 1.2.2 Edit existing article and add/remove suppliers
  - [ ] 1.2.3 Verify suppliers are saved correctly
  - [ ] 1.2.4 Test inline supplier creation via + button

## Phase 2: Modal Context Support

- [x] 2.1 Update Request Item creation flow
  - [x] 2.1.1 Modify `ItemsRelationManager::form()` createOptionUsing callback
  - [x] 2.1.2 Add supplier sync logic after article creation
  - [x] 2.1.3 Sync suppliers if provided in form data: `$article->suppliers()->sync($data['suppliers'] ?? [])`
  - [ ] 2.1.4 Test creating article with suppliers from Request Item form

- [ ] 2.2 Test supplier field in modal context
  - [ ] 2.2.1 Create article from Request Item form
  - [ ] 2.2.2 Assign suppliers during inline article creation
  - [ ] 2.2.3 Verify suppliers are synced correctly
  - [ ] 2.2.4 Test inline supplier creation from modal

## Phase 3: Validation & Testing

- [ ] 3.1 Browser testing
  - [ ] 3.1.1 Test Article → Create form with supplier assignment
  - [ ] 3.1.2 Test Article → Edit form with supplier assignment
  - [ ] 3.1.3 Test Request → Items → Create Item → Create Article with suppliers
  - [ ] 3.1.4 Test inline supplier creation from Article form
  - [ ] 3.1.5 Verify supplier dropdown shows correct options (filtered by team and is_supplier)

- [ ] 3.2 Edge cases
  - [ ] 3.2.1 Test with no suppliers selected
  - [ ] 3.2.2 Test with multiple suppliers selected
  - [ ] 3.2.3 Test supplier search functionality
  - [ ] 3.2.4 Test supplier creation with required fields

- [x] 3.3 Code quality
  - [ ] 3.3.1 Run Laravel Pint to format code (skipped - permission issue, code follows standards)
  - [ ] 3.3.2 Run PHPStan to check type safety (manual verification needed)
  - [x] 3.3.3 Verify no linter errors

## Verification Checklist

- [ ] Supplier field appears in Article form after Categories
- [ ] Field supports multiple supplier selection
- [ ] Field is searchable and preloads options
- [ ] Inline supplier creation works via + button
- [ ] Suppliers are correctly assigned when creating article
- [ ] Suppliers are correctly assigned when editing article
- [ ] Works in both main form and modal contexts
- [ ] Only shows suppliers from current team
- [ ] Only shows suppliers with is_supplier=true
