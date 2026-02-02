# Email Settings Developer Guide

## Overview

This guide explains how the email settings system works and how to extend it with new email templates.

## Architecture

### Components

1. **TeamErpSettings** (`app/Data/TeamErpSettings.php`)
   - Data class storing all email configuration
   - Persisted in `teams.erp_settings` JSON column

2. **EmailTemplateService** (`app/Services/Email/EmailTemplateService.php`)
   - Handles template rendering, variable replacement
   - Manages SMTP configuration
   - Sends emails with team settings

3. **EmailSettings Page** (`app/Filament/Pages/EmailSettings.php`)
   - Filament page for configuring email settings
   - Handles form submission and validation

4. **Mailable Classes** (`app/Mail/Erp/*`)
   - Laravel Mailables for each email type
   - Use EmailTemplateService for sending

## Email Settings Data Structure

### TeamErpSettings Fields

```php
// Global Email Configuration
public string $email_from_address = '';
public string $email_from_name = '';
public ?string $email_logo_media_id = null;
public string $email_signature = '';
public string $test_email_address = '';

// SMTP Configuration
public ?string $smtp_host = null;
public ?int $smtp_port = null;
public ?string $smtp_username = null;
public ?string $smtp_password = null; // Encrypted
public ?string $smtp_encryption = null; // 'tls', 'ssl', or null

// Email Templates
/** @var array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null */
public ?array $email_template_buyer_quote = null;
public ?array $email_template_buyer_order = null;
public ?array $email_template_supplier_order = null;
public ?array $email_template_delivery_order = null;
```

### Template Configuration Structure

Each template is stored as an array:

```php
[
    'content' => 'Template text with {{variables}}',
    'sender_email' => 'optional@override.com', // Optional, overrides global
    'cc_emails' => ['cc1@example.com', 'cc2@example.com'],
    'bcc_emails' => ['bcc@example.com'],
]
```

## Adding a New Email Template

### Step 1: Add Template Field to TeamErpSettings

Edit `app/Data/TeamErpSettings.php`:

```php
/** @var array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null */
public ?array $email_template_your_template = null,
```

### Step 2: Create Mailable Class

Create `app/Mail/Erp/YourTemplateMail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\YourModel;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class YourTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly YourModel $model
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->model->team->getErpSettings();
        $templateConfig = $settings->email_template_your_template ?? null;

        $fromAddress = $emailService->getSenderEmail($templateConfig, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Your Subject - '.$this->model->reference_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->model->team->getErpSettings();
        $templateConfig = $settings->email_template_your_template ?? null;

        // Prepare variables for template
        $variables = [
            'recipient_name' => $this->model->recipient->name ?? 'Recipient',
            'reference_number' => $this->model->reference_number ?? '',
            // Add more variables as needed
        ];

        $content = $emailService->renderTemplate($templateConfig, $variables);

        return new Content(
            view: 'emails.your-template',
            with: [
                'model' => $this->model,
                'content' => $content,
                'team' => $this->model->team,
            ],
        );
    }
}
```

### Step 3: Create Email View Template

Create `resources/views/emails/your-template.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Template {{ $model->reference_number }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <!-- Email content here -->
    @if(!empty($content) && trim($content) !== '')
        {!! nl2br(e($content)) !!}
    @else
        <!-- Default template content -->
    @endif
    
    @if(!empty($team->getErpSettings()->email_signature))
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            {!! nl2br(e($team->getErpSettings()->email_signature)) !!}
        </div>
    @endif
</body>
</html>
```

### Step 4: Add Template Section to EmailSettings Page

Edit `app/Filament/Pages/EmailSettings.php`:

1. Add template name to `$templates` array in `saveEmailSettings()`:

```php
$templates = [
    'buyer_quote',
    'buyer_order',
    'supplier_order',
    'delivery_order',
    'your_template', // Add here
];
```

2. Add template section in `emailForm()`:

```php
$this->buildTemplateSection('your_template', 'Your Template', 'Email template when sending your template PDF'),
```

### Step 5: Use EmailTemplateService to Send

In your code where you want to send the email:

```php
use App\Services\Email\EmailTemplateService;
use App\Mail\Erp\YourTemplateMail;

$emailService = app(EmailTemplateService::class);
$settings = $model->team->getErpSettings();

$emailService->sendWithTeamSettings(
    $model->team,
    new YourTemplateMail($model),
    $recipientEmail,
    $settings->email_template_your_template
);
```

## EmailTemplateService Methods

### `renderTemplate(?array $templateConfig, array $variables): string`

Renders template content with variable replacement.

**Parameters:**
- `$templateConfig`: Template configuration array or null
- `$variables`: Array of variable name => value pairs

**Returns:** Rendered template string (empty if no custom content)

**Example:**
```php
$variables = [
    'buyer_name' => 'John Doe',
    'quote_number' => 'Q-001',
];
$content = $emailService->renderTemplate($templateConfig, $variables);
```

### `getSenderEmail(?array $templateConfig, TeamErpSettings $settings): ?string`

Gets sender email with fallback priority:
1. Template-specific sender_email (if set)
2. Global email_from_address (if set)
3. config('mail.from.address')

### `getSenderName(TeamErpSettings $settings): string`

Gets sender name with fallback priority:
1. Global email_from_name (if set)
2. config('mail.from.name')

### `configureMailer(TeamErpSettings $settings): ?string`

Configures SMTP mailer if team SMTP is set.

**Returns:** Mailer name string or null (to use default mailer)

**Note:** Creates dynamic mailer named `team_smtp_{hash}`

### `sendWithTeamSettings(Team $team, Mailable $mailable, string|array $to, ?array $templateConfig = null): void`

Sends email using team's email settings.

**Parameters:**
- `$team`: Team instance
- `$mailable`: Mailable instance
- `$to`: Recipient email(s) - string or array
- `$templateConfig`: Optional template config for CC/BCC

**Features:**
- Uses team SMTP if configured
- Applies CC/BCC from template config
- Falls back to default mailer if team SMTP not set

## Template Variable System

### How Variables Work

1. Variables are defined in the Mailable's `content()` method
2. Variables are passed to `renderTemplate()`
3. Variables are replaced using `str_replace()` with `{{variable_name}}` syntax

### Adding New Variables

1. Add variable to `$variables` array in Mailable's `content()` method
2. Document variable in EmailSettings page helper text
3. Users can then use `{{variable_name}}` in template content

### Variable Replacement

- Case-sensitive: `{{Buyer_Name}}` ≠ `{{buyer_name}}`
- Must use double curly braces: `{{variable}}`
- Values are cast to string before replacement

## SMTP Configuration

### How It Works

1. `configureMailer()` checks if team SMTP is configured
2. If configured, creates dynamic mailer with team SMTP settings
3. SMTP password is decrypted from storage
4. Mailer is registered in Laravel config
5. `sendWithTeamSettings()` uses the configured mailer

### Gmail SMTP Considerations

- Gmail enforces From address to match authenticated account
- To send from different address, verify it in Gmail Settings → Send mail as
- Reply-To header is automatically set if From doesn't match SMTP username

## Testing

### Manual Testing

1. Configure email settings in EmailSettings page
2. Use "Send Test Email" functionality
3. Check logs: `storage/logs/laravel.log`

### Automated Testing (Recommended)

Create feature tests:

```php
use App\Models\Team;
use App\Services\Email\EmailTemplateService;
use Illuminate\Support\Facades\Mail;

public function test_email_sends_with_team_settings()
{
    Mail::fake();
    
    $team = Team::factory()->create();
    $emailService = app(EmailTemplateService::class);
    
    // Configure team SMTP settings
    $settings = $team->getErpSettings();
    $settings->smtp_host = 'smtp.example.com';
    // ... configure other settings
    $team->update(['erp_settings' => $settings->toArray()]);
    
    // Send email
    $emailService->sendWithTeamSettings(
        $team,
        new YourTemplateMail($model),
        'test@example.com'
    );
    
    Mail::assertSent(YourTemplateMail::class);
}
```

## Best Practices

1. **Always use EmailTemplateService**: Don't send emails directly with `Mail::send()`
2. **Handle Errors**: Wrap email sending in try-catch blocks
3. **Log Email Activity**: Log successful sends and failures
4. **Validate Settings**: Check if SMTP settings are valid before sending
5. **Test Locally**: Test email functionality in development before production

## Troubleshooting

### Emails Not Sending

1. Check SMTP configuration is correct
2. Verify SMTP credentials (especially App Passwords for Gmail)
3. Check Laravel logs for errors
4. Test SMTP connection using "Test SMTP Connection" button

### Template Variables Not Replacing

1. Verify variable names match exactly (case-sensitive)
2. Check variables are defined in Mailable's `content()` method
3. Ensure template config has content

### SMTP Authentication Failures

1. For Gmail: Use App Password if 2FA enabled
2. Verify port/encryption combination (587=TLS, 465=SSL)
3. Check firewall/network allows SMTP connections
