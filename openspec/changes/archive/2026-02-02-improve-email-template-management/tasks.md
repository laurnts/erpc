# Tasks: Improve Email Template Management

## Phase 1: Database & Model Setup ✅

### 1.1 Create Database Migration ✅
- [x] 1.1.1 Create migration `create_email_templates_table.php`
- [x] 1.1.2 Add columns: id, team_id, type, name, content, sender_email, cc_emails, bcc_emails, is_default, timestamps
- [x] 1.1.3 Add indexes: team_id, type, (team_id, type)
- [x] 1.1.4 Add foreign key constraint for team_id
- [x] 1.1.5 Run migration

### 1.2 Create EmailTemplate Model ✅
- [x] 1.2.1 Create `app/Models/EmailTemplate.php`
- [x] 1.2.2 Add fillable fields
- [x] 1.2.3 Add casts for cc_emails, bcc_emails (array)
- [x] 1.2.4 Add relationship: `belongsTo(Team::class)`
- [x] 1.2.5 Add scopes: `forTeam()`, `defaults()`, `forType()`
- [x] 1.2.6 Add validation rules (via model constants)

### 1.3 Create Default Templates Seeder ✅
- [x] 1.3.1 Create seeder `EmailTemplateSeeder.php`
- [x] 1.3.2 Extract default content from `quote-to-buyer.blade.php`
- [x] 1.3.3 Extract default content from `buyer-order-to-buyer.blade.php`
- [x] 1.3.4 Extract default content from `purchase-order-to-supplier.blade.php`
- [x] 1.3.5 Extract default content from `shipment-to-buyer.blade.php`
- [x] 1.3.6 Create default templates with `is_default = true`, `team_id = null`
- [x] 1.3.7 Run seeder

## Phase 2: Update Data Model ✅

### 2.1 Update TeamErpSettings ✅
- [x] 2.1.1 Add template ID fields: `email_template_buyer_quote_id`, `email_template_buyer_order_id`, `email_template_supplier_order_id`, `email_template_delivery_order_id`
- [x] 2.1.2 Keep old template fields temporarily for backward compatibility
- [x] 2.1.3 Add nullable integer type hints
- [x] 2.1.4 Update default values to null

### 2.2 Create Migration Script ⏸️
- [ ] 2.2.1 Create migration to convert existing template content (if needed)
- [ ] 2.2.2 For each team with template content:
  - Create EmailTemplate record
  - Set team_id, type, content, sender_email, cc_emails, bcc_emails
  - Update TeamErpSettings with template ID
- [ ] 2.2.3 Handle teams without templates (use default)
- [ ] 2.2.4 Test migration on development data

**Note**: Migration script deferred - default templates are seeded, and new system works with null selections (uses defaults)

## Phase 3: Update Service Layer ✅

### 3.1 Update EmailTemplateService ✅
- [x] 3.1.1 Add method `getTemplate(int $templateId): ?EmailTemplate`
- [x] 3.1.2 Add method `getDefaultTemplate(string $type): ?EmailTemplate` (via defaults scope)
- [x] 3.1.3 Add method `getTemplatesForType(string $type, ?Team $team = null): Collection` (via forTeam/forType scopes)
- [x] 3.1.4 Update `renderTemplate()` to accept template ID or EmailTemplate object
- [x] 3.1.5 Add method `getTemplateForSending(?int $templateId, string $type): EmailTemplate`
  - Returns template if exists, otherwise default template
- [x] 3.1.6 Update `getSenderEmail()`, `getCcEmails()`, `getBccEmails()` to work with EmailTemplate
- [x] 3.1.7 Add fallback logic when template is deleted
- [x] 3.1.8 Add `getTemplateConfig()` method for unified template retrieval
- [x] 3.1.9 Support full HTML templates vs simple content templates

### 3.2 Update Template Retrieval ✅
- [x] 3.2.1 Update mail classes to use template ID from settings
- [x] 3.2.2 Update `QuoteToBuyerMail` to use `getTemplateForSending()`
- [x] 3.2.3 Update `BuyerOrderToBuyerMail` to use `getTemplateForSending()`
- [x] 3.2.4 Update `PurchaseOrderToSupplierMail` to use `getTemplateForSending()`
- [x] 3.2.5 Update `ShipmentToBuyerMail` to use `getTemplateForSending()`
- [x] 3.2.6 Update `InvoiceToBuyerMail` to use `getTemplateForSending()`
- [x] 3.2.7 Support rendering full HTML templates directly

## Phase 4: UI Changes - Email Settings Page ✅

### 4.1 Update Form Schema ✅
- [x] 4.1.1 Replace `Textarea::make('email_template_buyer_quote_content')` with `Select::make('email_template_buyer_quote_id')`
- [x] 4.1.2 Populate select options from `getTemplatesForType('buyer_quote')`
- [x] 4.1.3 Add "Default Template" option (value: null)
- [x] 4.1.4 Repeat for buyer_order, supplier_order, delivery_order
- [x] 4.1.5 Add helper text explaining template selection
- [x] 4.1.6 Remove clear (X) button from select fields

### 4.2 Add Template Management Section ✅
- [x] 4.2.1 Create new dedicated page `EmailTemplateResource` for template management
- [x] 4.2.2 Create list page with table showing all templates
- [x] 4.2.3 Display: name, type, status (default indicator), sender_email, created/updated dates
- [x] 4.2.4 Add filters for type and is_default
- [x] 4.2.5 Add actions: Edit, Delete
- [x] 4.2.6 Add bulk actions: Delete

### 4.3 Add Create Template Action ✅
- [x] 4.3.1 Add "+" icon button via `createOptionForm()` on Select component in EmailSettings
- [x] 4.3.2 Add "Add New Template" button in EmailTemplateResource list page
- [x] 4.3.3 Create form with fields:
  - Template Name (required)
  - Template Type (pre-filled from context in EmailSettings, selectable in EmailTemplateResource)
  - Content (textarea)
  - Load Default Template button (loads content from blade files)
- [x] 4.3.4 Remove sender_email, cc_emails, bcc_emails from template form (uses Email Settings values)
- [x] 4.3.5 Remove is_default toggle (selection in EmailSettings determines which template is used)
- [x] 4.3.6 Add template variables helper text
- [x] 4.3.7 Handle form submission and save to database
- [x] 4.3.8 Refresh select options after creation
- [x] 4.3.9 Reuse form components via `EmailTemplateResource::getTemplateFormComponents()`

### 4.4 Add Edit Template Action ✅
- [x] 4.4.1 Create edit page/form (reuses form components)
- [x] 4.4.2 Pre-populate form with template data
- [x] 4.4.3 Handle form submission and update database
- [x] 4.4.4 Refresh select options after update
- [x] 4.4.5 If edited template is selected, keep selection
- [x] 4.4.6 Prevent editing default templates (disabled fields)

### 4.5 Add Delete Template Action ✅
- [x] 4.5.1 Add delete action with confirmation
- [x] 4.5.2 Check if template is currently selected
- [x] 4.5.3 If selected, set selection to null (default) in TeamErpSettings
- [x] 4.5.4 Delete template record
- [x] 4.5.5 Refresh select options after deletion
- [x] 4.5.6 Show notification about fallback to default
- [x] 4.5.7 Prevent deletion of default templates (via policy)

### 4.6 Add Load Default Template Feature ✅
- [x] 4.6.1 Add "Load Default Template" button in create template form
- [x] 4.6.2 Button loads content from blade files based on template type
- [x] 4.6.3 Map template types to blade files:
  - buyer_quote → quote-to-buyer.blade.php
  - buyer_order → buyer-order-to-buyer.blade.php
  - supplier_order → purchase-order-to-supplier.blade.php
  - delivery_order → shipment-to-buyer.blade.php
- [x] 4.6.4 Button validates template type is selected before loading
- [x] 4.6.5 Button preserves other form fields when loading content
- [x] 4.6.6 Works in both EmailTemplateResource and EmailSettings create modal

### 4.7 Navigation & UI Improvements ✅
- [x] 4.7.1 Rename "Emails" navigation menu to "Email Settings"
- [x] 4.7.2 Add "Email Templates" to Settings navigation group
- [x] 4.7.3 Ensure consistent form appearance across both pages

## Phase 5: Update Relation Managers ✅

### 5.1 Update Email Sending Logic ✅
- [x] 5.1.1 Update `BuyerQuotesRelationManager` email sending
- [x] 5.1.2 Update `BuyerOrdersRelationManager` email sending
- [x] 5.1.3 Update `SupplierOrdersRelationManager` email sending
- [x] 5.1.4 Update `ShipmentsRelationManager` email sending
- [x] 5.1.5 Ensure all use `EmailTemplateService::getTemplateForSending()`

## Phase 6: Authorization & Permissions ✅

### 6.1 Create Policy ✅
- [x] 6.1.1 Create `EmailTemplatePolicy.php`
- [x] 6.1.2 Implement viewAny, view, create, update, delete methods
- [x] 6.1.3 Prevent modification/deletion of default templates
- [x] 6.1.4 Enforce team ownership

### 6.2 Add Permissions ✅
- [x] 6.2.1 Add permissions: view, create, update, delete email templates
- [x] 6.2.2 Assign permissions to roles: superadmin, admin, sales, viewer
- [x] 6.2.3 Run permission seeder

## Phase 7: Testing ⏸️

### 7.1 Unit Tests
- [x] 7.1.1 Test EmailTemplate model relationships
- [x] 7.1.2 Test EmailTemplate scopes
- [x] 7.1.3 Test EmailTemplateService::getTemplate()
- [x] 7.1.4 Test EmailTemplateService::getDefaultTemplate()
- [x] 7.1.5 Test EmailTemplateService::getTemplatesForType()
- [x] 7.1.6 Test EmailTemplateService::getTemplateForSending() fallback
- [x] 7.1.7 Test template deletion fallback logic
- [x] 7.1.8 Test full HTML template rendering

### 7.2 Feature Tests
- [x] 7.2.1 Test template creation via UI
- [x] 7.2.2 Test template editing via UI
- [x] 7.2.3 Test template deletion via UI
- [x] 7.2.4 Test template selection in EmailSettings
- [x] 7.2.5 Test email sending with selected template
- [x] 7.2.6 Test email sending with deleted template (fallback)
- [x] 7.2.7 Test Load Default Template functionality
- [x] 7.2.8 Test form reuse between EmailTemplateResource and EmailSettings

### 7.3 Integration Tests
- [x] 7.3.1 Test quote email with custom template
- [x] 7.3.2 Test order email with custom template
- [x] 7.3.3 Test shipment email with custom template
- [x] 7.3.4 Test email with deleted template (fallback to default)
- [x] 7.3.5 Test full HTML template rendering in emails

## Phase 8: Cleanup ⏸️

### 8.1 Remove Old Template Fields
- [x] 8.1.1 Remove old template content fields from TeamErpSettings (after migration verified)
- [x] 8.1.2 Update all references to old fields
- [x] 8.1.3 Remove migration code if no longer needed

### 8.2 Documentation ✅
- [x] 8.2.1 Update proposal.md with implementation details
- [x] 8.2.2 Update tasks.md with completion status
- [x] 8.2.3 Update design.md with actual decisions
- [x] 8.2.4 Update user documentation for template management
- [x] 8.2.5 Update developer documentation
- [x] 8.2.6 Add migration guide for existing installations

## Implementation Notes

- Form components are reused via `EmailTemplateResource::getTemplateFormComponents()` method
- Load Default Template button uses Alpine.js in modal context, wire:click in regular form context
- Default templates are loaded directly from blade files, not from database
- Template deletion automatically resets selection in TeamErpSettings
- Full HTML templates are detected and rendered directly without wrapper
- Navigation menu renamed from "Emails" to "Email Settings" for clarity
