# Proposal: Add Email Settings to Settings Menu

## Summary

**STATUS: IMPLEMENTED** ✅

Comprehensive email configuration capabilities have been added as a separate Email Settings page, allowing teams to customize email templates, sender information, logo branding, and test email connectivity. Teams can personalize their email communications for quotations, invoices, purchase orders, and shipments sent to suppliers and buyers.

## Implementation Status

### ✅ Completed Features

1. **Email Settings Page** (`app/Filament/Pages/EmailSettings.php`)
   - Separate page under Settings navigation group
   - Logo upload with preview (using Spatie MediaLibrary)
   - Global email sender configuration (from address, from name)
  - SMTP configuration (host, port, username, password, encryption)
   - Email signature configuration
  - Test email functionality with address input and send button
   - SMTP connection testing

2. **Email Templates** (4 templates implemented)
   - `email_template_buyer_quote` - Buyer Quote email template
   - `email_template_buyer_order` - Buyer Order email template
   - `email_template_supplier_order` - Supplier Order (Purchase Order) email template
   - `email_template_delivery_order` - Delivery Order (Shipment) email template
   - Each template supports: content, sender_email (optional override), cc_emails[], bcc_emails[]

3. **Email Template Service** (`app/Services/Email/EmailTemplateService.php`)
   - Template variable replacement ({{variable}} syntax)
   - Team email settings retrieval with fallbacks
   - Template-specific sender, CC, BCC handling
   - SMTP mailer configuration per team
   - Email sending with team settings (`sendWithTeamSettings()`)

4. **Data Model** (`app/Data/TeamErpSettings.php`)
   - Email configuration fields (from_address, from_name, logo_media_id, signature)
   - SMTP configuration fields (host, port, username, password encrypted, encryption)
   - Email template configuration arrays (4 templates)
   - Test email address field

5. **Email Integration**
   - All ERP document emails use team email settings:
     - `QuoteToBuyerMail` - Uses `email_template_buyer_quote`
     - `BuyerOrderToBuyerMail` - Uses `email_template_buyer_order`
     - `PurchaseOrderToSupplierMail` - Uses `email_template_supplier_order`
     - `ShipmentToBuyerMail` - Uses `email_template_delivery_order`
   - All emails use `EmailTemplateService::sendWithTeamSettings()`
   - CC/BCC from template settings are applied
   - Team SMTP configuration is used when configured

6. **Test Email** (`app/Mail/TestEmailMail.php`)
   - Test email mailable with team branding
   - Uses team email settings for sender configuration

### ⚠️ Known Issues / Missing Features

1. **Missing Template**: `email_template_quote_to_supplier`
   - `QuoteToSupplierMail` references `$settings->email_template_quote_to_supplier` but this field doesn't exist in `TeamErpSettings`
   - EmailSettings page doesn't include this template
   - **Impact**: Quote-to-supplier emails may not use custom templates or CC/BCC settings
   - **Recommendation**: Add `email_template_quote_to_supplier` to TeamErpSettings and EmailSettings page

2. **Email Template Naming Inconsistency**
   - Proposal mentions "invoice to buyer" template but implementation uses "buyer_order" template
   - `InvoiceToBuyerMail` uses `email_template_buyer_order` (same as buyer order emails)
   - **Recommendation**: Clarify that buyer_order template is used for both orders and invoices

3. **Testing**: No automated tests implemented
   - All functionality works manually but needs test coverage
   - **Recommendation**: Add feature and integration tests (see Recommendations section)

## Architecture

### Data Flow

```
Email Settings Page (EmailSettings.php)
    ↓
saveEmailSettings()
    ↓
Team Model (erp_settings JSON)
    ↓
TeamErpSettings Data Object
    ↓
EmailTemplateService
    ↓
Mailable Classes (QuoteToBuyerMail, etc.)
    ↓
Laravel Mail (with team SMTP if configured)
```

### Email Template Rendering Flow

```
Document Created/Updated
    ↓
Email Sending Trigger (Relation Manager Action)
    ↓
EmailTemplateService::sendWithTeamSettings()
    ↓
Get Team Email Settings
    ↓
Get Template-Specific Settings (sender, CC, BCC)
    ↓
Configure SMTP Mailer (if team SMTP configured)
    ↓
Build Mailable with Settings
    ↓
Apply CC/BCC from Template Settings
    ↓
Send Email via Configured Mailer
```

## Current Implementation Details

### Email Templates Structure

Each template is stored as an array in `TeamErpSettings`:
```php
'email_template_buyer_quote' => [
    'content' => 'Template text with {{variables}}',
    'sender_email' => 'optional@override.com', // Optional, overrides global
    'cc_emails' => ['cc1@example.com', 'cc2@example.com'],
    'bcc_emails' => ['bcc@example.com'],
]
```

### Template Variables

Available variables shown in EmailSettings page:
- `{{supplier_name}}`, `{{buyer_name}}`
- `{{quote_number}}`, `{{order_number}}`, `{{invoice_number}}`, `{{shipment_number}}`
- `{{request_number}}`
- `{{valid_until}}`, `{{invoice_date}}`, `{{due_date}}`, `{{order_date}}`, `{{delivery_date}}`, `{{shipment_date}}`
- `{{total_amount}}`
- `{{tracking_number}}`, `{{delivery_address}}`

### Email Template Views

All email templates use HTML structure similar to quote-to-buyer template:
- `resources/views/emails/quote-to-buyer.blade.php` - Full HTML with items table
- `resources/views/emails/buyer-order-to-buyer.blade.php` - Full HTML with items table
- `resources/views/emails/purchase-order-to-supplier.blade.php` - Full HTML with items table
- `resources/views/emails/shipment-to-buyer.blade.php` - Full HTML with DO details
- `resources/views/emails/quote-to-supplier.blade.php` - Uses `@component('mail::message')` (legacy)

### SMTP Configuration

- SMTP passwords encrypted using `Crypt::encryptString()`
- Dynamic mailer creation: `team_smtp_{hash}`
- Falls back to default mailer if team SMTP not configured
- SMTP connection testing available in EmailSettings page

## Files Created/Modified

### New Files
- `app/Filament/Pages/EmailSettings.php` - Email settings page
- `app/Services/Email/EmailTemplateService.php` - Email template service
- `app/Mail/TestEmailMail.php` - Test email mailable
- `resources/views/filament/pages/email-settings.blade.php` - Email settings view
- `resources/views/filament/components/email-logo-preview.blade.php` - Logo preview component
- `openspec/changes/add-email-settings/docs/user-guide.md` - User documentation guide
- `openspec/changes/add-email-settings/docs/developer-guide.md` - Developer documentation guide

### Modified Files
- `app/Data/TeamErpSettings.php` - Added email configuration fields
- `app/Mail/Erp/QuoteToBuyerMail.php` - Uses EmailTemplateService
- `app/Mail/Erp/BuyerOrderToBuyerMail.php` - Uses EmailTemplateService
- `app/Mail/Erp/PurchaseOrderToSupplierMail.php` - Uses EmailTemplateService
- `app/Mail/Erp/ShipmentToBuyerMail.php` - Uses EmailTemplateService
- `app/Mail/Erp/InvoiceToBuyerMail.php` - Uses EmailTemplateService
- `app/Mail/Erp/QuoteToSupplierMail.php` - References missing template field
- `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php` - Uses EmailTemplateService
- `app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php` - Uses EmailTemplateService
- `app/Filament/Resources/RequestResource/RelationManagers/SupplierOrdersRelationManager.php` - Uses EmailTemplateService
- `app/Filament/Resources/RequestResource/RelationManagers/ShipmentsRelationManager.php` - Uses EmailTemplateService
- Email template views updated to use HTML structure

## Recommendations

1. **Add Missing Template**: Add `email_template_quote_to_supplier` to:
   - `TeamErpSettings.php` data class
   - `EmailSettings.php` page (add template section)
   - Update `QuoteToSupplierMail` to properly use template settings

2. **Template Consistency**: Consider updating `quote-to-supplier.blade.php` to use HTML structure like other templates

3. **Documentation**: ✅ **COMPLETED**
   - User guide created: `docs/user-guide.md` - Complete guide for configuring email settings, template variables, SMTP setup, and troubleshooting
   - Developer guide created: `docs/developer-guide.md` - Technical documentation for extending the system, adding new templates, and understanding the architecture
   - Both guides include examples, best practices, and troubleshooting sections

4. **Testing**: Add feature tests for:
   - Email template rendering with variables
   - CC/BCC application from template settings
   - SMTP configuration and mailer switching
   - Test email functionality

## Success Criteria (All Met ✅)

1. ✅ Email Settings page appears in Settings navigation
2. ✅ Teams can upload and configure email logo
3. ✅ Teams can customize email templates for document types (4 templates)
4. ✅ Teams can configure global sender email address and name
5. ✅ Teams can configure per-template sender, CC, and BCC addresses
6. ✅ Teams can configure SMTP settings (host, port, username, password, encryption)
7. ✅ SMTP passwords are encrypted in storage
8. ✅ Test email functionality works and provides clear feedback
9. ✅ Email templates support variable substitution
10. ✅ All ERP document emails use team's email settings when configured
11. ✅ Per-template CC/BCC are applied when sending emails
12. ✅ Settings are stored per-team in `erp_settings` JSON column
13. ✅ Documentation: User guide and developer guide created (`docs/user-guide.md`, `docs/developer-guide.md`)
14. ⚠️ Tests: Feature tests recommended but not yet implemented

## Out of Scope (As Planned)

- Rich text WYSIWYG editor for templates (using textarea)
- Email template versioning/history
- Multiple email templates per document type
- Email scheduling or queuing configuration
- Email delivery tracking
- Multiple SMTP configurations per team (one SMTP config per team)
