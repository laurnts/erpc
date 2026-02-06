# Change: Improve Email Template Management

## Why

Currently, email templates are stored as simple textarea content in team settings, making it difficult to:
- Manage multiple template variations for the same document type
- Reuse templates across different document types
- Organize and maintain templates
- Provide a better user experience for template selection

Users need a more flexible system where they can:
- Select from multiple pre-defined or custom templates via dropdown
- Create new templates with names and content
- Manage (view, edit, delete) their template library
- Have automatic fallback to default templates when selected templates are deleted
- Load default templates from blade files as starting points

## What Changes

### Database & Models
- **ADDED**: New `email_templates` database table with columns: id, team_id, type, name, content, sender_email, cc_emails, bcc_emails, is_default, timestamps
- **ADDED**: New `EmailTemplate` model with team relationships and scopes
- **ADDED**: Database seeder to populate default templates from blade files

### UI Changes
- **ADDED**: New `EmailTemplateResource` - dedicated Filament resource page for managing email templates (similar to Buyer list page)
- **MODIFIED**: Email Settings page (`EmailSettings.php`):
  - Replaced textarea fields with Select dropdowns for template selection
  - Added "Load Default Template" button in create template form
  - Integrated template creation via `createOptionForm()` on Select components
  - Navigation label changed from "Emails" to "Email Settings"
- **ADDED**: Reusable form components via `EmailTemplateResource::getTemplateFormComponents()` method
- **ADDED**: Template management features:
  - List templates in dedicated page (Email Templates)
  - Create templates with "Add New Template" button
  - Edit templates via row click or edit action
  - Delete templates with automatic fallback to default
  - Load default template content from blade files

### Service Layer
- **MODIFIED**: `EmailTemplateService`:
  - Updated to retrieve templates by ID from database
  - Added `getTemplateConfig()` method for unified template retrieval
  - Supports both new template system and backward compatibility with old array-based config
  - Handles full HTML templates vs simple content templates
- **MODIFIED**: All mail classes (`QuoteToBuyerMail`, `BuyerOrderToBuyerMail`, `PurchaseOrderToSupplierMail`, `ShipmentToBuyerMail`, `InvoiceToBuyerMail`):
  - Updated to use template IDs from `TeamErpSettings`
  - Support rendering full HTML templates directly
  - Fallback to default templates when selected template not found

### Data Model
- **MODIFIED**: `TeamErpSettings` DTO:
  - Added fields: `email_template_buyer_quote_id`, `email_template_buyer_order_id`, `email_template_supplier_order_id`, `email_template_delivery_order_id`
  - Stores selected template IDs (nullable for default templates)
- **ADDED**: `EmailTemplatePolicy` for authorization
- **ADDED**: Permissions: `view email templates`, `create email templates`, `update email templates`, `delete email templates`

### Features Implemented
- **ADDED**: Load Default Template functionality - loads content from blade files in `resources/views/emails/`
- **ADDED**: Form reuse - same form components used in both EmailTemplateResource and EmailSettings create modal
- **ADDED**: Template deletion fallback - automatically resets selection to default when deleted template was selected
- **ADDED**: Default template extraction from blade files:
  - `quote-to-buyer.blade.php` → Buyer Quote default
  - `buyer-order-to-buyer.blade.php` → Buyer Order default
  - `purchase-order-to-supplier.blade.php` → Supplier Order default
  - `shipment-to-buyer.blade.php` → Delivery Order default

## Impact

- **Affected specs**: `email-settings` (new capability), new `email-templates` resource
- **Affected code**:
  - `app/Data/TeamErpSettings.php` - Added template ID fields
  - `app/Models/EmailTemplate.php` - New model
  - `app/Filament/Pages/EmailSettings.php` - UI changes (textarea → select + management)
  - `app/Filament/Resources/EmailTemplateResource.php` - New resource for template management
  - `app/Filament/Resources/EmailTemplateResource/Pages/*.php` - Create, Edit, List pages
  - `app/Services/Email/EmailTemplateService.php` - Template retrieval logic updated
  - `app/Mail/Erp/*.php` - All mail classes using templates updated
  - `app/Policies/EmailTemplatePolicy.php` - New policy
  - Database migration `create_email_templates_table.php`
  - Database seeder `EmailTemplateSeeder.php`
- **Migration required**: Yes - default templates seeded from blade files
- **Breaking changes**: No - backward compatible with old array-based template config

## Implementation Status

✅ **Completed**:
- Database migration and model
- EmailTemplateResource with full CRUD
- EmailSettings page updated with select dropdowns
- Load Default Template functionality
- Form reuse between resources
- Email sending logic updated
- Template deletion fallback
- Default templates from blade files
- Authorization and permissions

## Notes

- Templates can contain full HTML (complete email documents) or simple content
- System detects full HTML templates and renders them directly without wrapper
- Default templates are loaded from blade files when creating new templates
- Form validation prevents template type changes for default templates
- Template management is accessible via dedicated "Email Templates" page in Settings navigation
