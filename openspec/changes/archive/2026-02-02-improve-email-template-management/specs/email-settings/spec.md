## MODIFIED Requirements

### Requirement: Email Template Management ✅
The system SHALL allow teams to manage multiple email templates per document type, select templates from a dropdown, and perform CRUD operations on templates.

#### Scenario: Select Template from Dropdown ✅
- **GIVEN** a user is configuring email settings
- **WHEN** they view the "Buyer Quote" template section
- **THEN** they see a dropdown select instead of textarea
- **AND** the dropdown shows available templates (default + custom templates)
- **AND** "Default Template" is shown as an option (value: null)
- **AND** the currently selected template is pre-selected
- **AND** the select field has no clear (X) button

**Implementation**: ✅ Completed via `Select::make('email_template_{type}_id')` in EmailSettings page

#### Scenario: Create New Template ✅
- **GIVEN** a user is viewing email template selection
- **WHEN** they click the "+" icon button next to the template select
- **THEN** a modal form opens with fields:
  - Template Type (pre-filled, disabled, based on section)
  - Load Default Template button (loads content from blade files)
  - Template Name (required text input)
  - Template Content (textarea with template variables helper)
- **WHEN** they fill the form and submit
- **THEN** a new template is created in the database
- **AND** the template is associated with the current team
- **AND** the select dropdown is refreshed with the new template
- **AND** the new template is automatically selected

**Implementation**: ✅ Completed via `createOptionForm()` on Select component, reuses `EmailTemplateResource::getTemplateFormComponents()`

#### Scenario: View Template List ✅
- **GIVEN** a user is viewing Email Settings or Email Templates page
- **WHEN** they navigate to "Email Templates" in Settings navigation
- **THEN** they see a list/table of templates
- **AND** each template shows: name, type, status (default indicator), sender_email, created/updated dates
- **AND** templates can be filtered by type and is_default
- **AND** templates are shown for current team

**Implementation**: ✅ Completed via `EmailTemplateResource` with table view, filters, and team scoping

#### Scenario: Edit Template ✅
- **GIVEN** a user is viewing the template list
- **WHEN** they click "Edit" on a template (or click row)
- **THEN** an edit form opens pre-populated with template data
- **WHEN** they modify fields and submit
- **THEN** the template is updated in the database
- **AND** if the edited template is currently selected, selection is maintained
- **AND** the template list is refreshed
- **AND** default templates have disabled fields (cannot be edited)

**Implementation**: ✅ Completed via `EditEmailTemplate` page, prevents editing default templates

#### Scenario: Delete Template ✅
- **GIVEN** a user is viewing the template list
- **WHEN** they click "Delete" on a template
- **THEN** a confirmation dialog appears
- **WHEN** they confirm deletion
- **THEN** the template is deleted from the database
- **AND** if the deleted template was selected, the selection is set to null (default template) in TeamErpSettings
- **AND** a notification explains that default template will be used
- **AND** the template list is refreshed
- **AND** default templates cannot be deleted

**Implementation**: ✅ Completed via `DeleteAction` in EmailTemplateResource, automatic fallback logic

#### Scenario: Load Default Template ✅
- **GIVEN** a user is creating a new template
- **WHEN** they select a template type
- **AND** they click "Load Default Template" button
- **THEN** the template content field is filled with content from the corresponding blade file:
  - buyer_quote → quote-to-buyer.blade.php
  - buyer_order → buyer-order-to-buyer.blade.php
  - supplier_order → purchase-order-to-supplier.blade.php
  - delivery_order → shipment-to-buyer.blade.php
- **AND** other form fields (template type, name) are preserved
- **AND** a success notification is shown

**Implementation**: ✅ Completed via `loadDefaultTemplate()` method in CreateEmailTemplate page, reads blade files directly

#### Scenario: Email Sending with Selected Template ✅
- **GIVEN** a team has selected a custom template for buyer quotes
- **WHEN** a quote is sent to a buyer
- **THEN** the email uses the selected template's content
- **AND** template variables are replaced with actual data
- **AND** the template's sender_email, cc_emails, bcc_emails are applied if configured (from Email Settings)
- **AND** full HTML templates are rendered directly without wrapper

**Implementation**: ✅ Completed via `EmailTemplateService::getTemplateForSending()`, mail classes updated

#### Scenario: Email Sending with Default Template ✅
- **GIVEN** a team has not selected a template (selection is null)
- **WHEN** a quote is sent to a buyer
- **THEN** the system uses the default template for that type
- **AND** default template content is used
- **AND** email is sent successfully

**Implementation**: ✅ Completed via fallback logic in `getTemplateForSending()`

#### Scenario: Fallback When Template Deleted ✅
- **GIVEN** a team has selected a custom template for buyer quotes
- **WHEN** that template is deleted
- **THEN** the team's template selection is automatically set to null in TeamErpSettings
- **AND** when emails are sent, the default template is used
- **AND** a notification informs the user about the fallback

**Implementation**: ✅ Completed via delete action handlers in both EmailTemplateResource and EmailSettings

#### Scenario: Default Templates Available ✅
- **GIVEN** a team has no custom templates
- **WHEN** they view template selection dropdowns
- **THEN** "Default Template" option is available for each type
- **AND** default templates are extracted from blade files:
  - buyer_quote: from `quote-to-buyer.blade.php`
  - buyer_order: from `buyer-order-to-buyer.blade.php`
  - supplier_order: from `purchase-order-to-supplier.blade.php`
  - delivery_order: from `shipment-to-buyer.blade.php`
- **AND** default templates are seeded in database with `is_default = true` and `team_id = null`

**Implementation**: ✅ Completed via `EmailTemplateSeeder`, default templates loaded from blade files

#### Scenario: Form Component Reuse ✅
- **GIVEN** a user is creating a template
- **WHEN** they view the create form in EmailTemplateResource
- **OR** they view the create form in EmailSettings modal
- **THEN** both forms have the same structure and appearance
- **AND** both forms include Load Default Template button
- **AND** form components are reused via `EmailTemplateResource::getTemplateFormComponents()`

**Implementation**: ✅ Completed via reusable form components method

## ADDED Requirements

### Requirement: Email Template Storage ✅
The system SHALL store email templates in a dedicated database table with support for multiple templates per type per team.

#### Scenario: Template Database Structure ✅
- **GIVEN** the system is initialized
- **WHEN** templates are stored
- **THEN** templates are stored in `email_templates` table with:
  - `id` (primary key)
  - `team_id` (foreign key, nullable for default templates)
  - `type` (enum: buyer_quote, buyer_order, supplier_order, delivery_order)
  - `name` (template display name)
  - `content` (template content with variables, supports full HTML)
  - `sender_email` (optional override, nullable)
  - `cc_emails` (JSON array, nullable)
  - `bcc_emails` (JSON array, nullable)
  - `is_default` (boolean flag)
  - `created_at`, `updated_at` timestamps
- **AND** indexes exist on team_id, type, and (team_id, type)

**Implementation**: ✅ Completed via migration `create_email_templates_table.php`

#### Scenario: Template Scoping ✅
- **GIVEN** multiple teams exist
- **WHEN** templates are created
- **THEN** templates are scoped to the creating team (`team_id`)
- **AND** default templates have `team_id = null` and `is_default = true`
- **AND** teams can only see/edit/delete their own templates
- **AND** default templates are visible to all teams but read-only (via policy)

**Implementation**: ✅ Completed via `EmailTemplate::scopeForTeam()`, `EmailTemplatePolicy`

#### Scenario: Template Selection Storage ✅
- **GIVEN** a team selects a template
- **WHEN** email settings are saved
- **THEN** the template ID is stored in `TeamErpSettings`:
  - `email_template_buyer_quote_id`
  - `email_template_buyer_order_id`
  - `email_template_supplier_order_id`
  - `email_template_delivery_order_id`
- **AND** null values indicate default template should be used

**Implementation**: ✅ Completed via `TeamErpSettings` DTO fields

### Requirement: Template Management UI ✅
The system SHALL provide a dedicated page for managing email templates.

#### Scenario: Email Templates List Page ✅
- **GIVEN** a user navigates to Settings > Email Templates
- **WHEN** they view the page
- **THEN** they see a table with columns: Name, Type, Status, Sender Email, Created, Updated
- **AND** they can filter by Type and Status (default)
- **AND** they can search templates by name
- **AND** they see an "Add New Template" button in the header
- **AND** they can click on a row to edit the template
- **AND** they can use Edit/Delete actions on each row

**Implementation**: ✅ Completed via `EmailTemplateResource` with table, filters, and actions

#### Scenario: Navigation Menu ✅
- **GIVEN** a user views the Settings navigation group
- **WHEN** they see the menu items
- **THEN** they see "Email Settings" (not "Emails")
- **AND** they see "Email Templates" as a separate menu item
- **AND** both are in the Settings navigation group

**Implementation**: ✅ Completed via `$navigationLabel = 'Email Settings'` and EmailTemplateResource navigation

### Requirement: Full HTML Template Support ✅
The system SHALL support both full HTML email templates and simple content templates.

#### Scenario: Full HTML Template Rendering ✅
- **GIVEN** a template contains complete HTML document (DOCTYPE, html, head, body tags)
- **WHEN** an email is sent using this template
- **THEN** the template is rendered directly without wrapper
- **AND** the full HTML is used as the email body
- **AND** template variables are replaced correctly

**Implementation**: ✅ Completed via `EmailTemplateService::renderTemplateContent()` detecting full HTML, mail classes checking `is_full_html` flag

#### Scenario: Simple Content Template Rendering ✅
- **GIVEN** a template contains simple text/content without full HTML structure
- **WHEN** an email is sent using this template
- **THEN** the content is injected into the default blade view wrapper
- **AND** template variables are replaced correctly

**Implementation**: ✅ Completed via fallback to default blade view when `is_full_html = false`

### Requirement: Template Migration ⏸️
The system SHALL migrate existing template content to the new template table structure.

#### Scenario: Migrate Existing Templates ⏸️
- **GIVEN** a team has existing template content in `TeamErpSettings`
- **WHEN** migration is run
- **THEN** for each existing template:
  - A new `EmailTemplate` record is created
  - Content, sender_email, cc_emails, bcc_emails are preserved
  - Template is associated with the team
  - Template ID is stored in `TeamErpSettings`
- **AND** teams without templates use default templates

**Status**: ⏸️ Deferred - system works with defaults when no templates exist, migration can be added if needed

#### Scenario: Migration Preserves Data ⏸️
- **GIVEN** a team has customized email templates
- **WHEN** migration is executed
- **THEN** all template content is preserved
- **AND** all sender, CC, BCC settings are preserved
- **AND** email sending continues to work with migrated templates

**Status**: ⏸️ Deferred - can be implemented when needed

## Implementation Notes

- Form components are reused via `EmailTemplateResource::getTemplateFormComponents()` method
- Load Default Template button uses different approaches for modal vs regular form contexts
- Default templates are loaded directly from blade files, not from database
- Template deletion automatically resets selection in TeamErpSettings
- Full HTML templates are detected and rendered directly without wrapper
- Navigation menu renamed from "Emails" to "Email Settings" for clarity
- Sender/CC/BCC settings follow Email Settings configuration, not stored in templates
- Default templates cannot be edited or deleted (enforced via policy and form)
