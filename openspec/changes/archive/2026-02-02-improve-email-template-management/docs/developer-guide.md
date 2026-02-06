# Email Template Management Developer Guide

## Overview

This guide explains how the email template management system works and how to extend it with new features or integrate it into your code.

## Architecture

### Components

1. **EmailTemplate Model** (`app/Models/EmailTemplate.php`)
   - Eloquent model for email templates
   - Stores template data in `email_templates` table
   - Team-scoped with default templates support

2. **EmailTemplateResource** (`app/Filament/Resources/EmailTemplateResource.php`)
   - Filament resource for managing templates
   - Provides CRUD operations via Filament pages
   - Reusable form components via `getTemplateFormComponents()`

3. **EmailTemplateService** (`app/Services/Email/EmailTemplateService.php`)
   - Handles template retrieval and rendering
   - Supports both new template system and backward compatibility
   - Detects full HTML templates vs simple content

4. **EmailSettings Page** (`app/Filament/Pages/EmailSettings.php`)
   - Filament page for configuring email settings
   - Template selection via Select dropdowns
   - Quick template creation via `createOptionForm()`

5. **TeamErpSettings** (`app/Data/TeamErpSettings.php`)
   - DTO storing selected template IDs
   - Fields: `email_template_{type}_id` (nullable integers)

6. **Mailable Classes** (`app/Mail/Erp/*`)
   - Laravel Mailables for each email type
   - Use `EmailTemplateService::getTemplateForSending()`

## Database Structure

### email_templates Table

```sql
CREATE TABLE email_templates (
    id BIGSERIAL PRIMARY KEY,
    team_id BIGINT NULL REFERENCES teams(id),
    type VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    sender_email VARCHAR(255) NULL,
    cc_emails JSON NULL,
    bcc_emails JSON NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_email_templates_team_id ON email_templates(team_id);
CREATE INDEX idx_email_templates_type ON email_templates(type);
CREATE INDEX idx_email_templates_team_type ON email_templates(team_id, type);
```

### Template Types

Defined as constants in `EmailTemplate` model:

```php
public const TYPE_BUYER_QUOTE = 'buyer_quote';
public const TYPE_BUYER_ORDER = 'buyer_order';
public const TYPE_SUPPLIER_ORDER = 'supplier_order';
public const TYPE_DELIVERY_ORDER = 'delivery_order';
```

## EmailTemplate Model

### Relationships

```php
public function team(): BelongsTo
{
    return $this->belongsTo(Team::class);
}
```

### Scopes

```php
// Get templates for a team (includes default templates)
EmailTemplate::forTeam($team)->get();

// Get default templates
EmailTemplate::defaults()->get();

// Get templates by type
EmailTemplate::forType('buyer_quote')->get();

// Combined
EmailTemplate::forTeam($team)
    ->forType('buyer_quote')
    ->get();
```

### Usage Example

```php
use App\Models\EmailTemplate;

// Get template by ID
$template = EmailTemplate::find($templateId);

// Get default template for type
$default = EmailTemplate::defaults()
    ->forType(EmailTemplate::TYPE_BUYER_QUOTE)
    ->first();

// Get team's templates
$teamTemplates = EmailTemplate::forTeam($team)
    ->forType(EmailTemplate::TYPE_BUYER_QUOTE)
    ->get();
```

## EmailTemplateService

### Key Methods

#### `getTemplateForSending(?int $templateId, string $type): EmailTemplate`

Retrieves a template for sending emails. Falls back to default if template not found.

```php
use App\Services\Email\EmailTemplateService;

$emailService = app(EmailTemplateService::class);
$settings = $team->getErpSettings();

$template = $emailService->getTemplateForSending(
    $settings->email_template_buyer_quote_id,
    EmailTemplate::TYPE_BUYER_QUOTE
);
```

#### `getTemplateConfig(?int $templateId, string $type, ?Team $team = null): array`

Gets complete template configuration including content, sender, CC, BCC.

```php
$config = $emailService->getTemplateConfig(
    $templateId,
    EmailTemplate::TYPE_BUYER_QUOTE,
    $team
);

// Returns:
[
    'content' => 'Template content...',
    'sender_email' => 'sender@example.com',
    'cc_emails' => ['cc@example.com'],
    'bcc_emails' => ['bcc@example.com'],
]
```

#### `renderTemplateContent(EmailTemplate|int|null $template, array $variables = []): array`

Renders template content with variable replacement. Returns array with content and is_full_html flag.

```php
$result = $emailService->renderTemplateContent($template, [
    'buyer_name' => 'John Doe',
    'quote_number' => 'Q-001',
]);

// Returns:
[
    'content' => 'Rendered content...',
    'is_full_html' => false, // or true if full HTML template
]
```

### Full HTML Template Detection

The service automatically detects full HTML templates by checking for HTML document structure:

```php
// Full HTML template (contains DOCTYPE, html, head, body tags)
$result['is_full_html'] = true;

// Simple content template
$result['is_full_html'] = false;
```

## Using Templates in Mailables

### Updated Mailable Pattern

```php
<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\Quote;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Blade;

final class QuoteToBuyerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quote $quote
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();
        
        $template = $emailService->getTemplateForSending(
            $settings->email_template_buyer_quote_id,
            \App\Models\EmailTemplate::TYPE_BUYER_QUOTE
        );
        
        $config = $emailService->getTemplateConfig(
            $template->id,
            \App\Models\EmailTemplate::TYPE_BUYER_QUOTE,
            $this->quote->team
        );

        $fromAddress = $emailService->getSenderEmail($config, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Quote ' . $this->quote->quote_number,
            from: $fromAddress,
            cc: $config['cc_emails'] ?? [],
            bcc: $config['bcc_emails'] ?? [],
        );
    }

    public function content(): Content
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();
        
        $template = $emailService->getTemplateForSending(
            $settings->email_template_buyer_quote_id,
            \App\Models\EmailTemplate::TYPE_BUYER_QUOTE
        );

        // Prepare variables
        $variables = [
            'buyer_name' => $this->quote->buyer->name ?? 'Buyer',
            'quote_number' => $this->quote->quote_number,
            'valid_until' => $this->quote->valid_until?->format('d M Y') ?? '',
            'total_amount' => $this->quote->currency?->formatNumber((float)$this->quote->total) ?? number_format((float)$this->quote->total, 2),
        ];

        // Render template
        $result = $emailService->renderTemplateContent($template, $variables);

        // Handle full HTML templates
        if ($result['is_full_html']) {
            $rendered = Blade::render($result['content'], [
                'quote' => $this->quote,
                'team' => $this->quote->team,
                ...$variables,
            ]);

            return new Content(
                htmlString: new \Illuminate\Support\HtmlString($rendered)
            );
        }

        // Simple content template - use default view
        return new Content(
            view: 'emails.quote-to-buyer',
            with: [
                'quote' => $this->quote,
                'team' => $this->quote->team,
                'content' => $result['content'],
            ],
        );
    }
}
```

## Reusable Form Components

### Using getTemplateFormComponents()

The `EmailTemplateResource::getTemplateFormComponents()` method provides reusable form components:

```php
use App\Filament\Resources\EmailTemplateResource;

// In EmailSettings createOptionForm
->createOptionForm(
    EmailTemplateResource::getTemplateFormComponents(
        defaultType: 'buyer_quote',
        showLoadButton: true,
        loadButtonMethod: 'loadDefaultTemplateForCreate',
        useAlpineJs: true,
        loadButtonParam: 'buyer_quote'
    )
)

// In EmailTemplateResource form
public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Template Information')
                ->schema(self::getTemplateFormComponents())
                ->columns(1),
        ]);
}
```

### Method Parameters

```php
public static function getTemplateFormComponents(
    ?string $defaultType = null,        // Pre-fill template type
    bool $showLoadButton = true,        // Show Load Default Template button
    ?string $loadButtonMethod = null,   // Livewire method name
    bool $useAlpineJs = false,          // Use Alpine.js vs wire:click
    ?string $loadButtonParam = null     // Additional parameter
): array
```

## Adding a New Template Type

### Step 1: Add Template Type Constant

Edit `app/Models/EmailTemplate.php`:

```php
public const TYPE_YOUR_TYPE = 'your_type';
```

### Step 2: Update TeamErpSettings

Edit `app/Data/TeamErpSettings.php`:

```php
public ?int $email_template_your_type_id = null;
```

### Step 3: Add Template Section to EmailSettings

Edit `app/Filament/Pages/EmailSettings.php`:

```php
// In buildTemplateSections() or similar method
$this->buildTemplateSection(
    'your_type',
    'Your Template Type',
    'Description of when this template is used'
);
```

### Step 4: Update EmailTemplateResource Options

Edit `app/Filament/Resources/EmailTemplateResource.php`:

```php
// In getTemplateFormComponents() method
Select::make('type')
    ->options([
        EmailTemplate::TYPE_BUYER_QUOTE => 'Buyer Quote',
        EmailTemplate::TYPE_BUYER_ORDER => 'Buyer Order',
        EmailTemplate::TYPE_SUPPLIER_ORDER => 'Supplier Order',
        EmailTemplate::TYPE_DELIVERY_ORDER => 'Delivery Order',
        EmailTemplate::TYPE_YOUR_TYPE => 'Your Type', // Add here
    ])
```

### Step 5: Create Default Template Blade File

Create `resources/views/emails/your-template.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Template</title>
</head>
<body>
    <!-- Default template content -->
</body>
</html>
```

### Step 6: Update EmailTemplateSeeder

Edit `database/seeders/EmailTemplateSeeder.php`:

```php
EmailTemplate::create([
    'team_id' => null,
    'type' => EmailTemplate::TYPE_YOUR_TYPE,
    'name' => 'Default Your Type Template',
    'content' => file_get_contents(resource_path('views/emails/your-template.blade.php')),
    'is_default' => true,
]);
```

### Step 7: Update Mailable Class

Update your mailable to use the new template type:

```php
$template = $emailService->getTemplateForSending(
    $settings->email_template_your_type_id,
    EmailTemplate::TYPE_YOUR_TYPE
);
```

## Load Default Template Feature

### Implementation in CreateEmailTemplate Page

```php
public function loadDefaultTemplate(): void
{
    $type = $this->data['type'] ?? null;
    
    if (!$type) {
        Notification::make()
            ->title('Template Type Required')
            ->body('Please select a template type first.')
            ->warning()
            ->send();
        return;
    }

    // Map type to blade file
    $templateFileMap = [
        EmailTemplate::TYPE_BUYER_QUOTE => 'emails/quote-to-buyer.blade.php',
        EmailTemplate::TYPE_BUYER_ORDER => 'emails/buyer-order-to-buyer.blade.php',
        // ... etc
    ];

    $bladeFilePath = $templateFileMap[$type] ?? null;
    $fullPath = resource_path("views/{$bladeFilePath}");
    
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $this->data['content'] = $content;
        $this->form->getComponent('content')->state($content);
        
        Notification::make()
            ->title('Default Template Loaded')
            ->body('Default template content has been loaded.')
            ->success()
            ->send();
    }
}
```

## Template Deletion Fallback

### Automatic Fallback Logic

When a template is deleted, the system automatically resets the selection in `TeamErpSettings`:

```php
// In EmailTemplateResource EditEmailTemplate page
DeleteAction::make()
    ->action(function (EmailTemplate $record) {
        $team = Filament::getTenant();
        $settings = $team->getErpSettings();
        
        // Check if this template is selected
        $typeField = "email_template_{$record->type}_id";
        if ($settings->$typeField === $record->id) {
            // Reset to default
            $settings->$typeField = null;
            $team->update(['erp_settings' => $settings->toArray()]);
        }
        
        $record->delete();
    })
```

## Authorization

### EmailTemplatePolicy

The `EmailTemplatePolicy` enforces:
- Teams can only view/edit/delete their own templates
- Default templates cannot be edited or deleted
- Permissions: `view email templates`, `create email templates`, `update email templates`, `delete email templates`

### Usage

```php
use App\Policies\EmailTemplatePolicy;

// Check authorization
$this->authorize('update', $template);
$this->authorize('delete', $template);
```

## Testing

### Unit Tests

```php
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Services\Email\EmailTemplateService;

public function test_get_template_for_sending_falls_back_to_default()
{
    $team = Team::factory()->create();
    $emailService = app(EmailTemplateService::class);
    
    // No template selected (null)
    $template = $emailService->getTemplateForSending(
        null,
        EmailTemplate::TYPE_BUYER_QUOTE
    );
    
    $this->assertTrue($template->is_default);
    $this->assertNull($template->team_id);
}

public function test_render_template_content_detects_full_html()
{
    $template = EmailTemplate::factory()->create([
        'content' => '<!DOCTYPE html><html><head>...</head><body>...</body></html>',
    ]);
    
    $emailService = app(EmailTemplateService::class);
    $result = $emailService->renderTemplateContent($template);
    
    $this->assertTrue($result['is_full_html']);
}
```

### Feature Tests

```php
use App\Models\EmailTemplate;
use App\Models\Team;
use Livewire\Livewire;

public function test_can_create_template_from_email_settings()
{
    $team = Team::factory()->create();
    
    Livewire::actingAs($team->owner)
        ->test(\App\Filament\Pages\EmailSettings::class)
        ->call('createTemplate', [
            'type' => EmailTemplate::TYPE_BUYER_QUOTE,
            'name' => 'Test Template',
            'content' => 'Test content',
        ]);
    
    $this->assertDatabaseHas('email_templates', [
        'team_id' => $team->id,
        'type' => EmailTemplate::TYPE_BUYER_QUOTE,
        'name' => 'Test Template',
    ]);
}
```

## Best Practices

1. **Always use EmailTemplateService**: Don't query templates directly
2. **Handle Template Not Found**: Always check if template exists before using
3. **Use Scopes**: Use model scopes (`forTeam`, `forType`, `defaults`) for queries
4. **Support Both Template Types**: Handle both full HTML and simple content templates
5. **Test Fallback Logic**: Ensure default templates are used when custom templates are deleted
6. **Validate Template Type**: Always validate template type matches expected type
7. **Use Reusable Components**: Leverage `getTemplateFormComponents()` for consistency

## Troubleshooting

### Template Not Found

- Check template ID exists in database
- Verify team scoping is correct
- Ensure template type matches

### Full HTML Not Detecting

- Check template content includes DOCTYPE, html, head, body tags
- Verify `renderTemplateContent()` returns correct `is_full_html` flag
- Check mail class handles `is_full_html` flag correctly

### Variables Not Replacing

- Ensure variables are passed to `renderTemplateContent()`
- Check variable names match exactly (case-sensitive)
- Verify template content uses `{{variable_name}}` syntax

### Form Not Loading Default Template

- Verify template type is selected before calling load method
- Check blade file exists in `resources/views/emails/`
- Ensure file is readable
