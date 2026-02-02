**STATUS: IMPLEMENTED** ✅

All requirements below have been implemented. See notes for any discrepancies or missing features.

## ADDED Requirements

### Requirement: Email Configuration
The system SHALL allow teams to configure email sender information, SMTP settings, and branding.

#### Scenario: Configure Global Email Sender
- **GIVEN** a user is the owner or admin of a team
- **WHEN** they navigate to Settings > Email Settings
- **THEN** they see fields for "From Email Address" and "From Name"
- **AND** default values are populated from global mail config if team settings not set

#### Scenario: Configure SMTP Settings
- **GIVEN** a user is configuring email settings
- **WHEN** they enter SMTP configuration (host, port, username, password, encryption)
- **THEN** the SMTP password is encrypted before storage
- **AND** SMTP settings are saved to team's email settings
- **AND** SMTP configuration can be tested before saving

#### Scenario: Upload Email Logo
- **GIVEN** a user is configuring email settings
- **WHEN** they upload an image file for email logo
- **THEN** the logo is stored via Spatie MediaLibrary
- **AND** the logo is displayed in email headers when sending emails
- **AND** file size is limited to 2MB
- **AND** supported formats are PNG, JPG, SVG

#### Scenario: Email Sender Fallback
- **GIVEN** a team has not configured email sender settings
- **WHEN** emails are sent from that team
- **THEN** the system uses global mail config (`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`)
- **AND** emails are sent successfully

#### Scenario: SMTP Configuration Fallback
- **GIVEN** a team has not configured SMTP settings
- **WHEN** emails are sent from that team
- **THEN** the system uses global SMTP configuration from `.env`
- **AND** emails are sent successfully

#### Scenario: SMTP Password Security
- **GIVEN** a user configures SMTP password
- **WHEN** the password is saved
- **THEN** the password is encrypted using Laravel Crypt
- **AND** the encrypted password is stored in the database
- **AND** the password is never logged or exposed in API responses

### Requirement: Email Template Management
The system SHALL allow teams to customize email templates for different document types.

#### Scenario: Configure Quote to Supplier Template ⚠️ **PARTIALLY IMPLEMENTED**
- **GIVEN** a user is configuring email settings
- **WHEN** they edit the "Quote Request to Supplier" template
- **THEN** ⚠️ **Template field missing** - `email_template_quote_to_supplier` not in TeamErpSettings
- **AND** ⚠️ **Not available in EmailSettings page**
- **NOTE**: `QuoteToSupplierMail` references this template but it doesn't exist. Should be added.

#### Scenario: Configure Quote to Buyer Template ✅ **IMPLEMENTED**
- **GIVEN** a user is configuring email settings
- **WHEN** they edit the "Buyer Quote" template (`email_template_buyer_quote`)
- **THEN** they can customize the email message ✅
- **AND** they can configure per-template sender, CC, and BCC addresses ✅
- **AND** template variables are available for dynamic content ✅
- **NOTE**: Template name is `buyer_quote` not `quote_to_buyer`

#### Scenario: Configure Invoice to Buyer Template ✅ **IMPLEMENTED**
- **GIVEN** a user is configuring email settings
- **WHEN** they edit the "Buyer Order" template (`email_template_buyer_order`)
- **THEN** they can customize the invoice email message ✅
- **AND** they can configure per-template sender, CC, and BCC addresses ✅
- **AND** template variables include invoice-specific fields ✅
- **NOTE**: Uses `buyer_order` template (same template used for both orders and invoices)

#### Scenario: Configure Purchase Order to Supplier Template ✅ **IMPLEMENTED**
- **GIVEN** a user is configuring email settings
- **WHEN** they edit the "Supplier Order" template (`email_template_supplier_order`)
- **THEN** they can customize the purchase order email message ✅
- **AND** they can configure per-template sender, CC, and BCC addresses ✅
- **AND** template variables include order-specific fields ✅
- **NOTE**: Template name is `supplier_order` not `purchase_to_supplier`

#### Scenario: Configure Shipment to Buyer Template ✅ **IMPLEMENTED**
- **GIVEN** a user is configuring email settings
- **WHEN** they edit the "Delivery Order" template (`email_template_delivery_order`)
- **THEN** they can customize the shipment notification email ✅
- **AND** they can configure per-template sender, CC, and BCC addresses ✅
- **AND** template variables include shipment-specific fields ✅
- **NOTE**: Template name is `delivery_order` not `shipment_to_buyer`

#### Scenario: Per-Template Sender Override
- **GIVEN** a team has configured global sender email and a template-specific sender email
- **WHEN** an email is sent using that template
- **THEN** the template-specific sender email is used
- **AND** the global sender is ignored for that template

#### Scenario: Per-Template CC/BCC Application
- **GIVEN** a team has configured CC and BCC addresses for a template
- **WHEN** an email is sent using that template
- **THEN** the configured CC addresses are included
- **AND** the configured BCC addresses are included
- **AND** CC/BCC are applied in addition to the primary recipient

#### Scenario: Template Variable Replacement
- **GIVEN** a team has configured an email template with variables
- **WHEN** an email is sent using that template
- **THEN** template variables are replaced with actual document data
- **AND** variables like {{supplier_name}} show the supplier's name
- **AND** variables like {{quote_number}} show the quote number

#### Scenario: Empty Template Fallback
- **GIVEN** a team has not configured an email template
- **WHEN** an email is sent for that document type
- **THEN** the system uses a default template
- **AND** the email is sent successfully

### Requirement: Test Email Functionality
The system SHALL provide a way to test email configuration.

#### Scenario: Send Test Email
- **GIVEN** a user is viewing Email Settings
- **WHEN** they enter an email address and click "Send Test Email"
- **THEN** a test email is sent to that address
- **AND** the test email includes the team's logo if configured
- **AND** the test email uses the team's sender information
- **AND** a success notification is displayed

#### Scenario: Test Email Failure
- **GIVEN** email configuration is incorrect (e.g., SMTP not configured)
- **WHEN** a user attempts to send a test email
- **THEN** an error notification is displayed with details
- **AND** the error message helps diagnose the issue

#### Scenario: Test Email Validation
- **WHEN** a user attempts to send a test email without entering an address
- **THEN** validation fails
- **AND** an error message prompts for an email address

### Requirement: Email Settings Persistence
The system SHALL store email settings per team.

#### Scenario: Save Email Settings
- **GIVEN** a user has configured email settings
- **WHEN** they click "Save Email Settings"
- **THEN** all settings are saved to the team's `erp_settings` JSON column
- **AND** a success notification is displayed
- **AND** settings persist across sessions

#### Scenario: Email Settings Isolation
- **GIVEN** two teams with different email configurations
- **WHEN** each team sends emails
- **THEN** each team uses its own email settings (logo, sender, templates)
- **AND** settings do not interfere with each other

#### Scenario: Load Email Settings
- **GIVEN** a team has previously configured email settings
- **WHEN** a user navigates to Email Settings
- **THEN** all previously saved settings are loaded and displayed
- **AND** form fields are populated with saved values

### Requirement: Email Template Usage
The system SHALL use team email templates when sending ERP document emails.

#### Scenario: Send Quote to Supplier with Template
- **GIVEN** a team has configured a quote-to-supplier email template with sender, CC, and BCC
- **WHEN** a quote is sent to a supplier
- **THEN** the email uses the team's custom template
- **AND** template variables are replaced with quote data
- **AND** the team's logo is included if configured
- **AND** the template-specific sender email is used (or global if not set)
- **AND** configured CC addresses are included
- **AND** configured BCC addresses are included
- **AND** if team SMTP is configured, email is sent via team SMTP

#### Scenario: Send Invoice to Buyer with Template
- **GIVEN** a team has configured an invoice-to-buyer email template with per-template settings
- **WHEN** an invoice is sent to a buyer
- **THEN** the email uses the team's custom template
- **AND** template variables are replaced with invoice data
- **AND** per-template CC/BCC are applied if configured

#### Scenario: Send Purchase Order with Template
- **GIVEN** a team has configured a purchase-order-to-supplier template with per-template settings
- **WHEN** a purchase order is sent to a supplier
- **THEN** the email uses the team's custom template
- **AND** template variables are replaced with order data
- **AND** per-template CC/BCC are applied if configured

#### Scenario: Send Shipment Notification with Template
- **GIVEN** a team has configured a shipment-to-buyer template with per-template settings
- **WHEN** a shipment notification is sent to a buyer
- **THEN** the email uses the team's custom template
- **AND** template variables are replaced with shipment data
- **AND** per-template CC/BCC are applied if configured

#### Scenario: Use Team SMTP Configuration
- **GIVEN** a team has configured SMTP settings
- **WHEN** an email is sent from that team
- **THEN** the email is sent using the team's SMTP configuration
- **AND** the SMTP password is decrypted for authentication
- **AND** the email is sent successfully via the configured SMTP server
