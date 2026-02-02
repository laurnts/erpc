# Design: Add Email Settings to Settings Menu

## Context

**STATUS: IMPLEMENTED** ✅

The application now has comprehensive email configuration capabilities. Teams can customize email branding, templates, and sender information for professional communication with suppliers and buyers. This aligns with the existing multi-tenant architecture where each team has independent ERP settings.

**Note**: Implementation differs slightly from original design - EmailSettings is a separate page rather than a section in Settings.php.

## Goals / Non-Goals

### Goals
- Enable teams to customize email appearance (logo, sender info)
- Provide email template customization for all ERP document types
- Allow teams to test email configuration before sending important communications
- Maintain consistency with existing Settings page pattern
- Support template variables for dynamic content

### Non-Goals
- Rich text WYSIWYG editor (start with textarea, can enhance later)
- Email template versioning or history
- Multiple templates per document type
- Email scheduling or delivery tracking
- Multiple SMTP configurations per team (one SMTP config per team)

## Decisions

### Decision: Store Email Settings in TeamErpSettings ✅ IMPLEMENTED
**Rationale**: Email settings are team-specific configuration, similar to other ERP settings. Storing in the existing `erp_settings` JSON column maintains consistency and avoids additional database tables.

**Implementation**: All email settings stored in `TeamErpSettings` data class, persisted in `teams.erp_settings` JSON column.

**Alternatives Considered**:
- Separate `email_settings` JSON column: Adds complexity, less consistent
- Separate `team_email_settings` table: Overkill for simple key-value pairs
- Global email settings: Doesn't support multi-tenancy

### Decision: Use Spatie MediaLibrary for Logo Storage ✅ IMPLEMENTED
**Rationale**: The application already uses Spatie MediaLibrary (found in Company model). Reusing existing infrastructure reduces complexity and maintains consistency.

**Implementation**: Logo stored in `email_logo` media collection on Team model, accessible via `getEmailLogoUrl()` method.

**Alternatives Considered**:
- Direct file storage: Less flexible, harder to manage
- Cloud storage only: Adds dependency, may not be needed for all teams

### Decision: Template Variables as Simple String Replacement ✅ IMPLEMENTED
**Rationale**: Start with simple `{{variable}}` syntax using Laravel's `Str::replace()` or similar. This is easy to understand, implement, and extend later if needed.

**Implementation**: Uses `str_replace()` in `EmailTemplateService::renderTemplate()` method. Variables shown in EmailSettings page helper text.

**Alternatives Considered**:
- Blade template compilation: More complex, potential security concerns
- Twig/Smarty: Adds dependency, overkill for simple use case
- Full templating engine: Unnecessary complexity for current needs

### Decision: Per-Template Email Configuration ✅ IMPLEMENTED
**Rationale**: Each document type may need different sender addresses, CC/BCC recipients. Storing per-template configuration provides flexibility while maintaining a global fallback.

**Implementation**: Each template stores `sender_email`, `cc_emails[]`, `bcc_emails[]` in template config array. Applied via `EmailTemplateService::sendWithTeamSettings()`.

**Alternatives Considered**:
- Global only: Less flexible, doesn't support different departments/roles per template
- Per-recipient configuration: Too complex, overkill for current needs

### Decision: SMTP Configuration in UI ✅ IMPLEMENTED
**Rationale**: Teams may use different SMTP providers (e.g., different departments use different email services). Allowing SMTP configuration per team provides flexibility while maintaining security through encryption.

**Implementation**: SMTP settings in EmailSettings page. Passwords encrypted with `Crypt::encryptString()`. Dynamic mailer created via `EmailTemplateService::configureMailer()`.

**Alternatives Considered**:
- Keep SMTP in `.env` only: Less flexible, requires server access to change
- Global SMTP only: Doesn't support multi-tenant email needs

### Decision: Encrypt SMTP Passwords ✅ IMPLEMENTED
**Rationale**: SMTP passwords are sensitive credentials. Use Laravel's built-in encryption (`Crypt::encrypt()`) to store passwords securely in the database.

**Implementation**: Uses `Crypt::encryptString()` for encryption, `Crypt::decryptString()` for decryption. Never logged or exposed in API responses.

**Alternatives Considered**:
- Plain text storage: Security risk, unacceptable
- Hashing: Cannot decrypt for SMTP authentication, not viable
- External secret management: Overkill for current scale

### Decision: Fallback to Global Config ✅ IMPLEMENTED
**Rationale**: When team email settings are not configured, fall back to `config('mail.from.address')`, `config('mail.from.name')`, and global SMTP config. This ensures emails always work even if team hasn't configured settings.

**Implementation**: `EmailTemplateService::getSenderEmail()` and `getSenderName()` fall back to config values. `configureMailer()` returns null if team SMTP not configured, using default mailer.

**Alternatives Considered**:
- Require team settings: Too strict, breaks existing functionality
- Use app defaults: Less flexible, doesn't leverage Laravel config

### Decision: Test Email as Inline Action ✅ IMPLEMENTED
**Rationale**: Include test email functionality directly in the Settings form as an action button. This provides immediate feedback without navigating to a separate page.

**Implementation**: Test email button in EmailSettings page header actions. SMTP connection test also available as separate action.

**Alternatives Considered**:
- Separate test email page: Adds navigation complexity
- CLI command only: Not accessible to non-technical users

## Architecture

### Data Flow

```
Settings Page Form
    ↓
saveEmailSettings()
    ↓
Team Model (erp_settings JSON)
    ↓
TeamErpSettings Data Object
    ↓
EmailTemplateService
    ↓
Mailable Classes
    ↓
Laravel Mail
```

### Email Template Rendering Flow

```
Document Created/Updated
    ↓
Email Sending Trigger
    ↓
EmailTemplateService::render()
    ↓
Get Team Email Settings
    ↓
Get Template-Specific Settings (sender, CC, BCC)
    ↓
Replace Template Variables
    ↓
Configure SMTP Mailer (if team SMTP configured)
    ↓
Build Mailable with Settings
    ↓
Apply CC/BCC from Template Settings
    ↓
Send Email via Configured Mailer
```

## Template Variable System ✅ IMPLEMENTED

### Available Variables (Shown in EmailSettings Page)

**All Templates Support:**
- `{{supplier_name}}` - Supplier company name
- `{{buyer_name}}` - Buyer company name
- `{{quote_number}}` - Quote reference number
- `{{order_number}}` - Order reference number
- `{{invoice_number}}` - Invoice reference number
- `{{shipment_number}}` - Shipment reference number
- `{{request_number}}` - Related request number
- `{{valid_until}}` - Quote expiration date
- `{{invoice_date}}` - Invoice date
- `{{due_date}}` - Payment due date
- `{{order_date}}` - Order date
- `{{delivery_date}}` - Expected delivery date
- `{{shipment_date}}` - Shipment date
- `{{total_amount}}` - Total amount
- `{{tracking_number}}` - Tracking number (if available)
- `{{delivery_address}}` - Delivery address

**Note**: Variables are replaced via `str_replace()` in `EmailTemplateService::renderTemplate()`. Template-specific variables are passed by each Mailable class.

### Variable Replacement Implementation ✅ IMPLEMENTED

```php
// EmailTemplateService::renderTemplate()
public function renderTemplate(?array $templateConfig, array $variables): string
{
    if (!$templateConfig || empty($templateConfig['content'])) {
        return '';
    }
    
    $content = trim($templateConfig['content']);
    
    foreach ($variables as $key => $value) {
        $content = str_replace("{{{$key}}}", (string) $value, $content);
    }
    
    return $content;
}
```

**Usage Example** (from QuoteToBuyerMail):
```php
$variables = [
    'buyer_name' => $quote->buyer->name ?? 'Buyer',
    'quote_number' => $quote->quote_number ?? '',
    'request_number' => $quote->request->request_number ?? '',
    'valid_until' => $quote->valid_until?->format('M j, Y') ?? '',
    'total_amount' => $totalAmount,
];
$content = $emailService->renderTemplate($templateConfig, $variables);
```

## Email Logo Handling

### Storage
- Logo stored via Spatie MediaLibrary in `email_logo` collection
- Accessible via `$team->getFirstMediaUrl('email_logo')`
- Fallback: No logo if not configured

### Display in Emails
- Include logo in email header if configured
- Recommended size: 200x60px
- Max file size: 2MB
- Supported formats: PNG, JPG, SVG

## Per-Template Email Configuration

### Structure
Each template has:
- `sender_email` (string|null): Overrides global sender if set
- `cc_emails` (array): List of CC recipients (comma-separated in UI)
- `bcc_emails` (array): List of BCC recipients (comma-separated in UI)

### Storage Format ✅ IMPLEMENTED

**Actual Implementation** (in TeamErpSettings):
```php
/** @var array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null */
public ?array $email_template_buyer_quote = null,
public ?array $email_template_buyer_order = null,
public ?array $email_template_supplier_order = null,
public ?array $email_template_delivery_order = null,
```

**Example Storage**:
```php
'email_template_buyer_quote' => [
    'content' => 'Template text with {{variables}}...',
    'sender_email' => 'quotes@example.com', // Optional override
    'cc_emails' => ['manager@example.com', 'sales@example.com'],
    'bcc_emails' => ['archive@example.com'],
]
```

**Note**: `email_template_quote_to_supplier` is referenced in `QuoteToSupplierMail` but missing from TeamErpSettings. Should be added.

## SMTP Configuration

### Storage
- SMTP settings stored in `TeamErpSettings`:
  - `smtp_host` (string|null)
  - `smtp_port` (int|null, default: 587)
  - `smtp_username` (string|null)
  - `smtp_password` (string|null, encrypted)
  - `smtp_encryption` (string|null, 'tls'|'ssl'|null)

### Security
- Passwords encrypted using `Crypt::encrypt()` before storage
- Passwords decrypted only when building mailer configuration
- Never logged or exposed in API responses

### Mailer Configuration ✅ IMPLEMENTED

**Actual Implementation** (`EmailTemplateService::configureMailer()`):
```php
public function configureMailer(TeamErpSettings $settings): ?string
{
    if (empty($settings->smtp_host)) {
        return null; // Use default mailer
    }

    $mailerName = 'team_smtp_'.md5($settings->smtp_host.$settings->smtp_port);

    $mailerConfig = [
        'transport' => 'smtp',
        'host' => $settings->smtp_host,
        'port' => $settings->smtp_port ?? 587,
        'encryption' => $settings->smtp_encryption,
        'username' => $settings->smtp_username,
        'password' => $settings->smtp_password ? Crypt::decryptString($settings->smtp_password) : null,
        'timeout' => null,
    ];

    config(["mail.mailers.{$mailerName}" => $mailerConfig]);

    return $mailerName;
}
```

### Fallback ✅ IMPLEMENTED
- If team SMTP not configured, `configureMailer()` returns `null`
- `sendWithTeamSettings()` uses default mailer when mailer is null
- Falls back to `config('mail.default')` automatically

## Risks / Trade-offs

### Risk: Template Variable Injection
**Mitigation**: Only allow predefined variables, sanitize all user input, use Laravel's escaping in Blade templates

### Risk: Email Sending Failures
**Mitigation**: Provide clear error messages, validate email addresses, test email functionality, fallback to global config

### Risk: SMTP Credential Security
**Mitigation**: Encrypt passwords using Laravel Crypt, never log passwords, validate SMTP settings before saving, provide test functionality

### Risk: SMTP Configuration Conflicts
**Mitigation**: Validate SMTP settings, test connection before saving, allow fallback to global config, clear error messages

### Risk: Logo Upload Issues
**Mitigation**: Validate file types and sizes, use proven MediaLibrary package, provide clear error messages

### Risk: Performance Impact
**Mitigation**: Settings loaded via Team model (already cached), template rendering is lightweight string operations

### Trade-off: Simple Templates vs Rich Editor
**Decision**: Start with textarea for simplicity, can add rich editor later if needed. Most users prefer simple text anyway.

## Migration Plan

### Phase 1: Add Settings (No Breaking Changes)
- Extend TeamErpSettings with email fields
- Add Settings page UI
- No impact on existing email sending

### Phase 2: Integrate Email Settings
- Update email sending code to use team settings
- Maintain fallback to global config
- Existing emails continue to work

### Phase 3: Template System
- Create email template service
- Update document email sending to use templates
- Gradual rollout, can disable per team if needed

### Rollback Plan
- If issues arise, can temporarily disable email settings usage
- Fallback to global config ensures emails continue working
- No data loss (settings stored in JSON, can be ignored)

## Open Questions

- Should we support HTML in email templates? (Start with plain text, add HTML later if needed)
- Should we validate template variables before saving? (Start without validation, add if needed)
- Should we provide template examples/previews? (Can add later as enhancement)
