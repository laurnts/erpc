# Design: Improve Email Template Management

## Context

Currently, email templates are stored as inline content arrays in `TeamErpSettings`:
```php
'email_template_buyer_quote' => [
    'content' => '...',
    'sender_email' => '...',
    'cc_emails' => [...],
    'bcc_emails' => [...],
]
```

This approach limits flexibility and makes it difficult to manage multiple template variations.

## Goals

1. ✅ Enable multiple templates per document type
2. ✅ Provide template management UI (CRUD operations)
3. ✅ Extract default templates from blade files
4. ✅ Maintain backward compatibility during migration
5. ✅ Provide seamless fallback when templates are deleted
6. ✅ Reuse form components across different contexts
7. ✅ Load default templates from blade files as starting points

## Non-Goals

- Template versioning/history
- Template sharing between teams
- Rich text WYSIWYG editor (keep textarea for now)
- Template preview functionality (can be added later)
- Duplicate template action (deferred)

## Decisions

### Decision: Database Table Structure ✅

**What**: Created `email_templates` table with:
- `id` (primary key)
- `team_id` (foreign key, nullable for default templates)
- `type` (enum: buyer_quote, buyer_order, supplier_order, delivery_order)
- `name` (string, template display name)
- `content` (text, template content with variables)
- `sender_email` (string, nullable)
- `cc_emails` (json, nullable)
- `bcc_emails` (json, nullable)
- `is_default` (boolean, indicates system default template)
- `created_at`, `updated_at`

**Why**: 
- Enables multiple templates per type
- Allows template reuse
- Supports team-scoped templates
- `is_default` flag helps identify system templates

**Alternatives considered**:
- Keep in JSON: Doesn't support multiple templates well
- Separate table per type: Over-normalization, harder to query

**Implementation**: ✅ Completed via migration `create_email_templates_table.php`

### Decision: Default Template Extraction ✅

**What**: Extract default content from blade templates:
- `quote-to-buyer.blade.php` → Default buyer_quote template
- `buyer-order-to-buyer.blade.php` → Default buyer_order template
- `purchase-order-to-supplier.blade.php` → Default supplier_order template
- `shipment-to-buyer.blade.php` → Default delivery_order template

**Why**: 
- Provides sensible defaults
- Users can see what default templates look like
- Can be customized or used as-is
- Allows loading default content when creating new templates

**Implementation**: 
- ✅ Created seeder `EmailTemplateSeeder.php` to extract content from blade files
- ✅ Marked as `is_default = true` and `team_id = null`
- ✅ Reads entire blade file content (full HTML templates)
- ✅ "Load Default Template" button loads content directly from blade files

### Decision: Template Selection Storage ✅

**What**: Store selected template ID in `TeamErpSettings`:
```php
'email_template_buyer_quote_id' => 123, // ID of selected template
```

**Why**:
- Simple reference
- Easy to query
- Supports null (use default)

**Alternatives considered**:
- Store template name: Could change, less reliable
- Store full template object: Redundant, harder to update

**Implementation**: ✅ Added fields to `TeamErpSettings` DTO

### Decision: Fallback Logic ✅

**What**: When selected template is deleted:
1. Check if template exists
2. If not found, set selection to `null`
3. Use default template (`is_default = true` for that type)

**Why**:
- Prevents broken references
- Graceful degradation
- User can select new template

**Implementation**:
- ✅ Added validation in `EmailTemplateService::getTemplateForSending()`
- ✅ Check template existence before sending
- ✅ Fallback to default template query
- ✅ Automatic reset in `EmailTemplatePolicy` delete method
- ✅ Automatic reset in `EmailSettings::deleteTemplate()` method

### Decision: UI Architecture ✅

**What**: 
- Dedicated `EmailTemplateResource` page for template management (similar to Buyer list page)
- EmailSettings page uses Select dropdowns with `createOptionForm()` for quick creation
- Form components reused via `EmailTemplateResource::getTemplateFormComponents()` method
- "Load Default Template" button in create forms

**Why**:
- Better UX for template selection
- Clear separation between selection and management
- Familiar pattern (similar to other Filament resources)
- Code reuse reduces duplication
- Consistent form appearance

**Components**:
- ✅ `Select::make('email_template_buyer_quote_id')` with options from templates
- ✅ `createOptionForm()` on Select for quick template creation
- ✅ `EmailTemplateResource` with table for template list
- ✅ `Action::make('edit')` and `Action::make('delete')` for each template
- ✅ Reusable form components method

**Implementation**: ✅ Completed

### Decision: Form Component Reuse ✅

**What**: Created `EmailTemplateResource::getTemplateFormComponents()` static method that returns form components array, reusable in:
- EmailTemplateResource create/edit pages
- EmailSettings createOptionForm modal

**Why**:
- Single source of truth for form structure
- Easier maintenance
- Consistent UI/UX
- Supports different contexts (regular form vs modal)

**Parameters**:
- `defaultType`: Pre-fills template type (for EmailSettings context)
- `showLoadButton`: Whether to show Load Default Template button
- `loadButtonMethod`: Livewire method to call
- `useAlpineJs`: Use Alpine.js for modal contexts vs wire:click for regular forms
- `loadButtonParam`: Additional parameter (e.g., template type key)

**Implementation**: ✅ Completed

### Decision: Load Default Template Feature ✅

**What**: "Load Default Template" button that:
- Validates template type is selected
- Reads content directly from blade files in `resources/views/emails/`
- Fills the content textarea without clearing other fields
- Works in both EmailTemplateResource and EmailSettings contexts

**Why**:
- Provides starting point for new templates
- Users can see default template structure
- Saves time when creating custom templates
- Maintains consistency with system defaults

**Implementation**:
- ✅ Button in EmailTemplateResource uses `wire:click.prevent="loadDefaultTemplate"`
- ✅ Button in EmailSettings modal uses Alpine.js `$wire.call()`
- ✅ Method reads blade files directly from filesystem
- ✅ Preserves template type selection when loading content

### Decision: Full HTML Template Support ✅

**What**: System detects and supports both:
- Full HTML templates (complete email documents)
- Simple content templates (injected into blade wrapper)

**Why**:
- Users may want complete control over email HTML
- Backward compatible with simple content
- Flexible template system

**Implementation**:
- ✅ `EmailTemplateService::renderTemplateContent()` detects full HTML
- ✅ Returns `['content' => string, 'is_full_html' => bool]`
- ✅ Mail classes check `is_full_html` flag
- ✅ Full HTML templates rendered directly via `Blade::render()`
- ✅ Simple content uses default blade view wrapper

### Decision: Template Form Fields ✅

**What**: Template form includes:
- Template Type (required, disabled for default templates)
- Template Name (required)
- Template Content (required, textarea)
- Load Default Template button (visible when creating)

**Removed from form**:
- Sender Email, CC Emails, BCC Emails (uses Email Settings values)
- is_default toggle (selection in EmailSettings determines usage)

**Why**:
- Simpler form focused on template content
- Sender/CC/BCC should follow Email Settings configuration
- Selection determines which template is used, not is_default flag

**Implementation**: ✅ Completed

### Decision: Navigation Menu ✅

**What**: Renamed "Emails" menu item to "Email Settings"

**Why**:
- More descriptive name
- Clarifies purpose of the page

**Implementation**: ✅ Added `$navigationLabel = 'Email Settings'` to EmailSettings page

## Risks / Trade-offs

### Risk: Migration Complexity ✅ Mitigated
**Risk**: Existing template content needs migration to new table
**Mitigation**: 
- ✅ Default templates seeded from blade files
- ✅ System works with null selections (uses defaults)
- ✅ Migration script deferred (can be added if needed)
- ✅ Backward compatibility maintained

### Risk: Breaking Changes ✅ Mitigated
**Risk**: Mail classes expect old format
**Mitigation**:
- ✅ Updated all mail classes to use new service method
- ✅ Backward compatibility layer in `EmailTemplateService`
- ✅ Updated all relation managers using templates
- ✅ Graceful fallback to defaults

### Risk: Default Template Extraction ✅ Mitigated
**Risk**: Blade templates may change, extraction might miss content
**Mitigation**:
- ✅ Reads entire blade file content (full HTML)
- ✅ "Load Default Template" loads directly from files
- ✅ Seeder extracts complete file content
- ✅ Manual review possible

### Trade-off: Template Storage ✅ Accepted
**Trade-off**: Moving from JSON to table adds complexity but enables better management
**Decision**: Accept complexity for better UX and scalability
**Result**: ✅ Successfully implemented with good UX

### Trade-off: Form Complexity ✅ Accepted
**Trade-off**: Reusable form components add abstraction but reduce duplication
**Decision**: Accept abstraction for maintainability
**Result**: ✅ Clean implementation with good code reuse

## Migration Plan

### Phase 1: Database & Model ✅
1. ✅ Create `email_templates` migration
2. ✅ Create `EmailTemplate` model with relationships
3. ✅ Create seeder for default templates (extract from blade files)

### Phase 2: Service Layer ✅
1. ✅ Update `EmailTemplateService` to work with template IDs
2. ✅ Add methods: `getTemplate()`, `getDefaultTemplate()`, `getTemplatesForType()`, `getTemplateConfig()`
3. ✅ Update `renderTemplate()` to accept template ID or object
4. ✅ Support full HTML templates

### Phase 3: UI Changes ✅
1. ✅ Update `EmailSettings` page form (textarea → select)
2. ✅ Create `EmailTemplateResource` for template management
3. ✅ Add create/edit/delete actions
4. ✅ Add Load Default Template functionality
5. ✅ Implement form component reuse

### Phase 4: Mail Integration ✅
1. ✅ Update all mail classes to use template IDs
2. ✅ Update relation managers to use new template selection
3. ✅ Test all email sending flows

### Phase 5: Data Migration ⏸️
1. ⏸️ Create migration script to convert existing templates (deferred)
2. ⏸️ Run migration for all teams (if needed)
3. ⏸️ Verify template content preserved

**Note**: Migration script deferred as system works with defaults when no templates exist

### Rollback Plan
- ✅ Old template fields kept in `TeamErpSettings` during transition
- ✅ Can revert to old format if issues arise
- ✅ Migration script can be added later if needed

## Open Questions

1. ✅ Should default templates be editable or read-only?
   - **Decision**: Editable, but disabled via form (can be reset to original)
   
2. ✅ Should we support template duplication/cloning?
   - **Decision**: Deferred - can be added later if needed

3. ✅ Should template names be unique per type per team?
   - **Decision**: Not enforced - allows flexibility

4. ✅ How to handle template variables documentation?
   - **Decision**: Helper text in template form shows available variables

5. ✅ Should sender/CC/BCC be in template form or Email Settings?
   - **Decision**: Email Settings - templates use Email Settings configuration

6. ✅ How to load default templates when creating new templates?
   - **Decision**: "Load Default Template" button reads directly from blade files

## Implementation Summary

✅ **Completed Features**:
- Database table and model
- EmailTemplateResource with full CRUD
- EmailSettings page with select dropdowns
- Load Default Template functionality
- Form component reuse
- Email sending with template support
- Template deletion fallback
- Default templates from blade files
- Authorization and permissions
- Full HTML template support
- Navigation menu improvements

**Key Achievements**:
- Clean separation of concerns
- Code reuse via reusable form components
- Backward compatibility maintained
- User-friendly template management
- Flexible template system supporting both simple and full HTML templates
