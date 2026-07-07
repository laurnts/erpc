<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Data\TeamErpSettings;
use App\Filament\Pages\EditTeam;
use App\Filament\Resources\EmailTemplateResource;
use App\Mail\TestEmailMail;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Services\Email\EmailTemplateService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Psr\Log\LoggerInterface;

/**
 * @property Schema $emailForm
 */
final class EmailSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use WithRateLimiting;

    public ?string $createTemplateType = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 16;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Email Settings';

    protected static ?string $slug = 'emails';

    protected string $view = 'filament.pages.email-settings';

    /** @var array<string, mixed>|null */
    public ?array $emailData = [];

    public function getTitle(): string
    {
        return 'Email Settings';
    }

    public static function getNavigationLabel(): string
    {
        return 'Emails';
    }

    public function mount(): void
    {
        /** @var Team $team */
        $team = Filament::getTenant();
        $settings = $team->getErpSettings();
        $emailService = app(EmailTemplateService::class);
        $smtpPassword = $this->decryptSmtpPassword($settings->smtp_password);

        $this->emailForm->fill([
            'test_email_address' => $settings->test_email_address ?? '',
            'email_from_address' => $settings->email_from_address ?: config('mail.from.address'),
            'email_from_name' => $settings->email_from_name ?: config('mail.from.name'),
            'smtp_host' => $settings->smtp_host,
            'smtp_port' => $settings->smtp_port,
            'smtp_username' => $settings->smtp_username,
            'smtp_password' => $smtpPassword,
            'smtp_encryption' => $settings->smtp_encryption,
            'email_signature' => $settings->email_signature,
            // Template IDs (new system) - will be null initially, populated after migration
            'email_template_buyer_quote_id' => $settings->email_template_buyer_quote_id ?? null,
            'email_template_buyer_order_id' => $settings->email_template_buyer_order_id ?? null,
            'email_template_supplier_order_id' => $settings->email_template_supplier_order_id ?? null,
            'email_template_delivery_order_id' => $settings->email_template_delivery_order_id ?? null,
            // Template settings (loaded from selected template or old format for backward compatibility)
            'email_template_buyer_quote_sender' => $this->getTemplateSender($settings, 'buyer_quote'),
            'email_template_buyer_quote_cc' => $this->getTemplateCc($settings, 'buyer_quote'),
            'email_template_buyer_quote_bcc' => $this->getTemplateBcc($settings, 'buyer_quote'),
            'email_template_buyer_order_sender' => $this->getTemplateSender($settings, 'buyer_order'),
            'email_template_buyer_order_cc' => $this->getTemplateCc($settings, 'buyer_order'),
            'email_template_buyer_order_bcc' => $this->getTemplateBcc($settings, 'buyer_order'),
            'email_template_supplier_order_sender' => $this->getTemplateSender($settings, 'supplier_order'),
            'email_template_supplier_order_cc' => $this->getTemplateCc($settings, 'supplier_order'),
            'email_template_supplier_order_bcc' => $this->getTemplateBcc($settings, 'supplier_order'),
            'email_template_delivery_order_sender' => $this->getTemplateSender($settings, 'delivery_order'),
            'email_template_delivery_order_cc' => $this->getTemplateCc($settings, 'delivery_order'),
            'email_template_delivery_order_bcc' => $this->getTemplateBcc($settings, 'delivery_order'),
        ]);
    }

    private function decryptSmtpPassword(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            $this->logger()->warning('Failed to decrypt SMTP password on Email Settings page', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function logger(): LoggerInterface
    {
        return app(LoggerInterface::class);
    }

    /**
     * Get template sender email from selected template or old format.
     */
    private function getTemplateSender(TeamErpSettings $settings, string $type): string
    {
        $templateIdField = "email_template_{$type}_id";
        if (isset($settings->{$templateIdField}) && $settings->{$templateIdField}) {
            $template = EmailTemplate::find($settings->{$templateIdField});
            if ($template && $template->sender_email) {
                return $template->sender_email;
            }
        }

        // Fallback to old format
        $oldField = "email_template_{$type}";
        return $settings->{$oldField}['sender_email'] ?? '';
    }

    /**
     * Get template CC emails from selected template or old format.
     */
    private function getTemplateCc(TeamErpSettings $settings, string $type): string
    {
        $templateIdField = "email_template_{$type}_id";
        if (isset($settings->{$templateIdField}) && $settings->{$templateIdField}) {
            $template = EmailTemplate::find($settings->{$templateIdField});
            if ($template && $template->cc_emails) {
                return implode(', ', $template->cc_emails);
            }
        }

        // Fallback to old format
        $oldField = "email_template_{$type}";
        return isset($settings->{$oldField}['cc_emails']) ? implode(', ', $settings->{$oldField}['cc_emails']) : '';
    }

    /**
     * Get template BCC emails from selected template or old format.
     */
    private function getTemplateBcc(TeamErpSettings $settings, string $type): string
    {
        $templateIdField = "email_template_{$type}_id";
        if (isset($settings->{$templateIdField}) && $settings->{$templateIdField}) {
            $template = EmailTemplate::find($settings->{$templateIdField});
            if ($template && $template->bcc_emails) {
                return implode(', ', $template->bcc_emails);
            }
        }

        // Fallback to old format
        $oldField = "email_template_{$type}";
        return isset($settings->{$oldField}['bcc_emails']) ? implode(', ', $settings->{$oldField}['bcc_emails']) : '';
    }

    /**
     * @return array<string>
     */
    protected function getForms(): array
    {
        return [
            'emailForm',
        ];
    }

    public function emailForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Email Header')
                    ->schema([
                        ViewField::make('current_logo')
                            ->label('Current Logo')
                            ->view('filament.components.email-logo-preview')
                            ->visible(function (): bool {
                                /** @var Team $team */
                                $team = Filament::getTenant();

                                return $team->getEmailLogoUrl() !== null;
                            }),

                        Placeholder::make('email_logo_info')
                            ->label('Company Logo')
                            ->content('The company logo from Edit Team → Branding is used in email templates.')
                            ->helperText(fn (): string => 'Manage branding at: '.EditTeam::getUrl()),

                        TextInput::make('email_from_address')
                            ->label('Default Sender Email')
                            ->email()
                            ->required()
                            ->default(config('mail.from.address'))
                            ->helperText('Default email address used as sender for all emails. If using Gmail SMTP, verify this address in Gmail Settings → Accounts → Send mail as to send from a different domain.'),

                        TextInput::make('email_from_name')
                            ->label('Default Sender Name')
                            ->default(config('mail.from.name'))
                            ->helperText('Default name displayed as sender for all emails'),

                        Textarea::make('email_signature')
                            ->label('Email Signature')
                            ->rows(3)
                            ->helperText('Signature appended to all email templates'),
                    ])
                    ->collapsible(),

                Section::make('SMTP Configuration')
                    ->schema([
                        TextInput::make('smtp_host')
                            ->label('SMTP Host')
                            ->helperText('Leave empty to use default mailer configuration'),

                        TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->default(587)
                            ->helperText('Common ports: 587 (TLS), 465 (SSL), 25'),

                        TextInput::make('smtp_username')
                            ->label('SMTP Username'),

                        TextInput::make('smtp_password')
                            ->label('SMTP Password')
                            ->password()
                            ->helperText('Password is encrypted when saved. For Gmail with 2FA: Use App Password (generate at https://myaccount.google.com/apppasswords). IMPORTANT: Remove all spaces from App Password before pasting (e.g., "abcd efgh" → "abcdefgh").')
                            ->extraAttributes(['autocomplete' => 'new-password']),

                        Select::make('smtp_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                null => 'None',
                            ])
                            ->default('tls'),
                    ])
                    ->collapsed(),

                Section::make('Email Templates')
                    ->schema([
                        Placeholder::make('template_variables_info')
                            ->label('Available Template Variables')
                            ->content('{{supplier_name}}, {{buyer_name}}, {{quote_number}}, {{order_number}}, {{invoice_number}}, {{shipment_number}}, {{request_number}}, {{valid_until}}, {{total_amount}}, {{invoice_date}}, {{due_date}}, {{order_date}}, {{delivery_date}}, {{shipment_date}}, {{tracking_number}}, {{delivery_address}}'),

                        $this->buildTemplateSection('buyer_quote', 'Buyer Quote', 'Email template when sending buyer quote PDF'),
                        $this->buildTemplateSection('buyer_order', 'Buyer Order', 'Email template when sending buyer order PDF'),
                        $this->buildTemplateSection('supplier_order', 'Supplier Order', 'Email template when sending supplier order (purchase order) PDF'),
                        $this->buildTemplateSection('delivery_order', 'Delivery Order', 'Email template when sending delivery order (shipment) PDF'),
                    ])
                    ->collapsed(),

                Section::make('Template Management')
                    ->schema([
                        Placeholder::make('template_management_info')
                            ->label('Manage Your Templates')
                            ->content('View, edit, or delete your custom email templates. Default templates cannot be deleted.'),
                        
                        ViewField::make('template_list')
                            ->view('filament.components.email-template-list')
                            ->viewData(function () {
                                /** @var Team $team */
                                $team = Filament::getTenant();
                                return [
                                    'templates' => EmailTemplate::forTeam($team)
                                        ->orderBy('type')
                                        ->orderBy('is_default', 'desc')
                                        ->orderBy('name')
                                        ->get()
                                        ->groupBy('type'),
                                    'types' => [
                                        EmailTemplate::TYPE_BUYER_QUOTE => 'Buyer Quote',
                                        EmailTemplate::TYPE_BUYER_ORDER => 'Buyer Order',
                                        EmailTemplate::TYPE_SUPPLIER_ORDER => 'Supplier Order',
                                        EmailTemplate::TYPE_DELIVERY_ORDER => 'Delivery Order',
                                    ],
                                ];
                            }),
                    ])
                    ->collapsed(),

                Section::make('Test Email')
                    ->schema([
                        TextInput::make('test_email_address')
                            ->label('Test Email Address')
                            ->email()
                            ->helperText('Send a test email to verify your email configuration'),
                    ]),
            ])
            ->statePath('emailData');
    }

    /**
     * Build a template section with select dropdown and plus icon to create new template.
     */
    private function buildTemplateSection(string $key, string $label, string $helperText): Section
    {
        /** @var Team $team */
        $team = Filament::getTenant();

        return Section::make($label)
            ->schema([
                Select::make("email_template_{$key}_id")
                    ->label('Email Template')
                    ->options(function () use ($key, $team): array {
                        $templates = EmailTemplate::forTeam($team)
                            ->forType($key)
                            ->orderBy('is_default', 'desc')
                            ->orderBy('name')
                            ->get();

                        $options = [null => 'Default Template'];
                        foreach ($templates as $template) {
                            $name = $template->name;
                            if ($template->is_default) {
                                $name .= ' (Default)';
                            }
                            $options[$template->id] = $name;
                        }

                        return $options;
                    })
                    
                    ->helperText($helperText . ' Select a template or use + to create a new one.')
                    ->createOptionForm(
                        EmailTemplateResource::getTemplateFormComponents(
                            defaultType: $key,
                            showLoadButton: true,
                            loadButtonMethod: 'loadDefaultTemplateForCreate',
                            useAlpineJs: true,
                            loadButtonParam: $key
                        )
                    )
                    ->createOptionUsing(function (array $data, $get) use ($key, $team): int {
                        // Get sender/CC/BCC from the existing template section fields (if set)
                        $emailService = app(EmailTemplateService::class);
                        
                        // Get values from form state - these come from the template section fields below
                        $senderEmail = $get("email_template_{$key}_sender") ? trim($get("email_template_{$key}_sender")) : null;
                        $ccEmails = $get("email_template_{$key}_cc") ? trim($get("email_template_{$key}_cc")) : null;
                        $bccEmails = $get("email_template_{$key}_bcc") ? trim($get("email_template_{$key}_bcc")) : null;

                        $template = EmailTemplate::create([
                            'team_id' => $team->id,
                            'type' => $key,
                            'name' => $data['name'],
                            'content' => $data['content'],
                            'sender_email' => !empty($senderEmail) ? $senderEmail : null,
                            'cc_emails' => !empty($ccEmails) ? $emailService->parseEmailList($ccEmails) : null,
                            'bcc_emails' => !empty($bccEmails) ? $emailService->parseEmailList($bccEmails) : null,
                            'is_default' => false,
                        ]);

                        Notification::make()
                            ->title('Template Created')
                            ->body("Template '{$template->name}' has been created successfully.")
                            ->success()
                            ->send();

                        return $template->id;
                    })
                    ->createOptionAction(fn (Action $action): Action => $action->modalWidth('2xl'))
                    ->live()
                    ->afterStateUpdated(function ($state, $set) use ($key): void {
                        // When template is selected, load its sender/CC/BCC if available
                        if ($state) {
                            $template = EmailTemplate::find($state);
                            if ($template) {
                                $set("email_template_{$key}_sender", $template->sender_email ?? '');
                                $set("email_template_{$key}_cc", $template->cc_emails ? implode(', ', $template->cc_emails) : '');
                                $set("email_template_{$key}_bcc", $template->bcc_emails ? implode(', ', $template->bcc_emails) : '');
                            }
                        } else {
                            // Reset to empty when default is selected
                            $set("email_template_{$key}_sender", '');
                            $set("email_template_{$key}_cc", '');
                            $set("email_template_{$key}_bcc", '');
                        }
                    }),

                TextInput::make("email_template_{$key}_sender")
                    ->label('Sender Email (Optional)')
                    ->email()
                    ->helperText('Overrides global sender email for this template'),

                TextInput::make("email_template_{$key}_cc")
                    ->label('CC Emails')
                    ->helperText('Comma-separated email addresses'),

                TextInput::make("email_template_{$key}_bcc")
                    ->label('BCC Emails')
                    ->helperText('Comma-separated email addresses'),
            ])
            ->collapsed();
    }

    /**
     * Create a new email template.
     */
    public function createTemplate(array $data): void
    {
        /** @var Team $team */
        $team = Filament::getTenant();
        $emailService = app(EmailTemplateService::class);

        $template = EmailTemplate::create([
            'team_id' => $team->id,
            'type' => $data['type'] ?? $this->createTemplateType,
            'name' => $data['name'],
            'content' => $data['content'],
            'sender_email' => !empty($data['sender_email']) ? $data['sender_email'] : null,
            'cc_emails' => !empty($data['cc_emails']) ? $emailService->parseEmailList($data['cc_emails']) : null,
            'bcc_emails' => !empty($data['bcc_emails']) ? $emailService->parseEmailList($data['bcc_emails']) : null,
            'is_default' => false,
        ]);

        Notification::make()
            ->title('Template Created')
            ->body("Template '{$template->name}' has been created successfully.")
            ->success()
            ->send();

        $this->createTemplateType = null;
        
        // Refresh the form to show new template in select and auto-select it
        $this->mount();
        $this->emailForm->fill([
            "email_template_{$template->type}_id" => $template->id,
        ]);
    }

    /**
     * Delete an email template and handle fallback.
     */
    public function deleteTemplate(int $templateId): void
    {
        /** @var Team $team */
        $team = Filament::getTenant();
        $template = EmailTemplate::find($templateId);

        if (!$template) {
            Notification::make()
                ->title('Template Not Found')
                ->body('The template you are trying to delete does not exist.')
                ->danger()
                ->send();
            return;
        }

        // Check if template belongs to team or is default
        if ($template->is_default || $template->team_id === null) {
            Notification::make()
                ->title('Cannot Delete Default Template')
                ->body('Default templates cannot be deleted.')
                ->warning()
                ->send();
            return;
        }

        if ($template->team_id !== $team->id) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You can only delete templates belonging to your team.')
                ->danger()
                ->send();
            return;
        }

        $templateType = $template->type;
        $templateName = $template->name;

        // Check if this template is currently selected
        $settings = $team->getErpSettings();
        $templateIdField = "email_template_{$templateType}_id";
        $isSelected = isset($settings->{$templateIdField}) && $settings->{$templateIdField} === $templateId;

        // Delete the template
        $template->delete();

        // If template was selected, reset to default (null)
        if ($isSelected) {
            $updatedSettings = new TeamErpSettings(
                company_name: $settings->company_name,
                company_address: $settings->company_address,
                company_phone: $settings->company_phone,
                company_email: $settings->company_email,
                default_currency: $settings->default_currency,
                default_tax_percent: $settings->default_tax_percent,
                quote_validity_days: $settings->quote_validity_days,
                default_payment_terms_days: $settings->default_payment_terms_days,
                prices_include_tax: $settings->prices_include_tax,
                default_margin_percent: $settings->default_margin_percent,
                request_number_prefix: $settings->request_number_prefix,
                project_number_prefix: $settings->project_number_prefix,
                buyer_quote_number_prefix: $settings->buyer_quote_number_prefix,
                buyer_order_number_prefix: $settings->buyer_order_number_prefix,
                supplier_order_number_prefix: $settings->supplier_order_number_prefix,
                shipment_number_prefix: $settings->shipment_number_prefix,
                buyer_invoice_number_prefix: $settings->buyer_invoice_number_prefix,
                supplier_invoice_number_prefix: $settings->supplier_invoice_number_prefix,
                buyer_payment_number_prefix: $settings->buyer_payment_number_prefix,
                supplier_payment_number_prefix: $settings->supplier_payment_number_prefix,
                email_from_address: $settings->email_from_address,
                email_from_name: $settings->email_from_name,
                email_logo_media_id: $settings->email_logo_media_id,
                email_signature: $settings->email_signature,
                test_email_address: $settings->test_email_address,
                smtp_host: $settings->smtp_host,
                smtp_port: $settings->smtp_port,
                smtp_username: $settings->smtp_username,
                smtp_password: $settings->smtp_password,
                smtp_encryption: $settings->smtp_encryption,
                email_template_buyer_quote_id: $templateType === EmailTemplate::TYPE_BUYER_QUOTE ? null : $settings->email_template_buyer_quote_id,
                email_template_buyer_order_id: $templateType === EmailTemplate::TYPE_BUYER_ORDER ? null : $settings->email_template_buyer_order_id,
                email_template_supplier_order_id: $templateType === EmailTemplate::TYPE_SUPPLIER_ORDER ? null : $settings->email_template_supplier_order_id,
                email_template_delivery_order_id: $templateType === EmailTemplate::TYPE_DELIVERY_ORDER ? null : $settings->email_template_delivery_order_id,
                email_template_buyer_quote: $settings->email_template_buyer_quote,
                email_template_buyer_order: $settings->email_template_buyer_order,
                email_template_supplier_order: $settings->email_template_supplier_order,
                email_template_delivery_order: $settings->email_template_delivery_order,
            );

            $team->erp_settings = $updatedSettings;
            $team->save();
        }

        Notification::make()
            ->title('Template Deleted')
            ->body($isSelected 
                ? "Template '{$templateName}' has been deleted. The default template will now be used."
                : "Template '{$templateName}' has been deleted successfully.")
            ->success()
            ->send();

        // Refresh the form
        $this->mount();
    }

    public function saveEmailSettings(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Too many requests')
                ->body("Please wait {$exception->secondsUntilAvailable} seconds before trying again.")
                ->danger()
                ->send();

            return;
        }

        /** @var Team $team */
        $team = Filament::getTenant();
        $currentSettings = $team->getErpSettings();
        $emailData = $this->emailForm->getState();
        $emailService = app(EmailTemplateService::class);

        // Logo is handled by afterStateUpdated hook when uploaded
        // Preserve the existing logoMediaId (which may have been updated by afterStateUpdated)
        // Refresh team to get latest logo media ID in case it was updated by afterStateUpdated
        $team->refresh();
        $updatedSettings = $team->getErpSettings();
        $logoMediaId = $updatedSettings->email_logo_media_id ?? $currentSettings->email_logo_media_id;
        
        // Fallback: if logoMediaId is null but there's a logo in media collection, use the latest one
        if (empty($logoMediaId)) {
            $latestLogo = $team->getMedia('email_logo')->sortByDesc('created_at')->first();
            if ($latestLogo) {
                $logoMediaId = (string) $latestLogo->id;
            }
        }

        // Encrypt SMTP password if provided
        $smtpPassword = $currentSettings->smtp_password;
        if (! empty($emailData['smtp_password'])) {
            // Trim password to remove any accidental whitespace (especially important for Gmail App Passwords)
            $passwordToEncrypt = trim($emailData['smtp_password']);
            if (! empty($passwordToEncrypt)) {
                $smtpPassword = Crypt::encryptString($passwordToEncrypt);
            }
        }

        // Save template IDs and update template settings if modified
        $templateTypes = [
            'buyer_quote',
            'buyer_order',
            'supplier_order',
            'delivery_order',
        ];

        $templateIds = [];
        foreach ($templateTypes as $type) {
            $templateId = $emailData["email_template_{$type}_id"] ?? null;
            $templateIds["email_template_{$type}_id"] = $templateId ? (int) $templateId : null;

            // If a template is selected and sender/CC/BCC are modified, update the template
            if ($templateId) {
                $template = EmailTemplate::find($templateId);
                if ($template && $template->team_id === $team->id) {
                    // Only update if it's a team template (not default)
                    $sender = !empty($emailData["email_template_{$type}_sender"]) ? trim($emailData["email_template_{$type}_sender"]) : null;
                    $cc = trim($emailData["email_template_{$type}_cc"] ?? '');
                    $bcc = trim($emailData["email_template_{$type}_bcc"] ?? '');

                    $template->update([
                        'sender_email' => $sender,
                        'cc_emails' => !empty($cc) ? $emailService->parseEmailList($cc) : null,
                        'bcc_emails' => !empty($bcc) ? $emailService->parseEmailList($bcc) : null,
                    ]);
                }
            }
        }

        // Keep old template format for backward compatibility (empty arrays)
        $templateConfigs = [];
        foreach ($templateTypes as $template) {
            $templateConfigs["email_template_{$template}"] = [
                'content' => '',
                'sender_email' => null,
                'cc_emails' => [],
                'bcc_emails' => [],
            ];
        }

        $settings = new TeamErpSettings(
            company_name: $currentSettings->company_name,
            company_address: $currentSettings->company_address,
            company_phone: $currentSettings->company_phone,
            company_email: $currentSettings->company_email,
            default_currency: $currentSettings->default_currency,
            default_tax_percent: $currentSettings->default_tax_percent,
            quote_validity_days: $currentSettings->quote_validity_days,
            default_payment_terms_days: $currentSettings->default_payment_terms_days,
            prices_include_tax: $currentSettings->prices_include_tax,
            default_margin_percent: $currentSettings->default_margin_percent,
            request_number_prefix: $currentSettings->request_number_prefix,
            project_number_prefix: $currentSettings->project_number_prefix,
            buyer_quote_number_prefix: $currentSettings->buyer_quote_number_prefix,
            buyer_order_number_prefix: $currentSettings->buyer_order_number_prefix,
            supplier_order_number_prefix: $currentSettings->supplier_order_number_prefix,
            shipment_number_prefix: $currentSettings->shipment_number_prefix,
            buyer_invoice_number_prefix: $currentSettings->buyer_invoice_number_prefix,
            supplier_invoice_number_prefix: $currentSettings->supplier_invoice_number_prefix,
            buyer_payment_number_prefix: $currentSettings->buyer_payment_number_prefix,
            supplier_payment_number_prefix: $currentSettings->supplier_payment_number_prefix,
            email_from_address: $emailData['email_from_address'] ?? '',
            email_from_name: $emailData['email_from_name'] ?? '',
            email_logo_media_id: $logoMediaId,
            email_signature: $emailData['email_signature'] ?? '',
            test_email_address: $emailData['test_email_address'] ?? '',
            smtp_host: ! empty($emailData['smtp_host']) ? $emailData['smtp_host'] : null,
            smtp_port: ! empty($emailData['smtp_port']) ? (int) $emailData['smtp_port'] : null,
            smtp_username: ! empty($emailData['smtp_username']) ? $emailData['smtp_username'] : null,
            smtp_password: $smtpPassword,
            smtp_encryption: ! empty($emailData['smtp_encryption']) ? $emailData['smtp_encryption'] : null,
            email_template_buyer_quote_id: $templateIds['email_template_buyer_quote_id'],
            email_template_buyer_order_id: $templateIds['email_template_buyer_order_id'],
            email_template_supplier_order_id: $templateIds['email_template_supplier_order_id'],
            email_template_delivery_order_id: $templateIds['email_template_delivery_order_id'],
            email_template_buyer_quote: $templateConfigs['email_template_buyer_quote'],
            email_template_buyer_order: $templateConfigs['email_template_buyer_order'],
            email_template_supplier_order: $templateConfigs['email_template_supplier_order'],
            email_template_delivery_order: $templateConfigs['email_template_delivery_order'],
        );

        $team->erp_settings = $settings;
        $team->save();
        
        // Refresh the team model to ensure latest data
        $team->refresh();

        // Reload form with updated settings
        $this->mount();

        $this->sendNotification('Email Settings Saved', 'Email configuration has been updated successfully.');
    }

    public function sendTestEmail(): void
    {
        $emailData = $this->emailForm->getState();
        $testEmail = $emailData['test_email_address'] ?? null;

        if (! $testEmail) {
            Notification::make()
                ->title('Test Email Address Required')
                ->body('Please enter a test email address.')
                ->danger()
                ->send();

            return;
        }

        try {
            /** @var Team $team */
            $team = Filament::getTenant();
            $emailService = app(EmailTemplateService::class);
            $settings = $team->getErpSettings();

            $mailer = $emailService->configureMailer($settings);
            $mailable = new TestEmailMail($team);

            if ($mailer) {
                Mail::mailer($mailer)->to($testEmail)->send($mailable);
            } else {
                Mail::to($testEmail)->send($mailable);
            }

            Notification::make()
                ->title('Test Email Sent')
                ->body("Test email sent successfully to {$testEmail}. Please check your inbox.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Provide helpful guidance for common Gmail errors
            if (str_contains($errorMessage, '535') || str_contains($errorMessage, 'Username and Password not accepted')) {
                $errorMessage .= "\n\nTroubleshooting:\n";
                $errorMessage .= "• Ensure App Password has NO spaces (remove spaces from generated password)\n";
                $errorMessage .= "• Verify username is the full email address\n";
                $errorMessage .= "• Check port/encryption: 465=SSL, 587=TLS\n";
                $errorMessage .= "• Regenerate App Password if issue persists";
            }
            
            Notification::make()
                ->title('Failed to Send Test Email')
                ->body($errorMessage)
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function testSmtpConnection(): void
    {
        $emailData = $this->emailForm->getState();

        if (empty($emailData['smtp_host'])) {
            Notification::make()
                ->title('SMTP Host Required')
                ->body('Please enter SMTP host to test connection.')
                ->danger()
                ->send();

            return;
        }

        try {
            $validator = Validator::make($emailData, [
                'smtp_host' => 'required|string',
                'smtp_port' => 'required|integer|min:1|max:65535',
                'smtp_username' => 'required_with:smtp_password|string',
                'smtp_password' => 'required_with:smtp_username|string',
            ]);

            if ($validator->fails()) {
                Notification::make()
                    ->title('Invalid SMTP Configuration')
                    ->body($validator->errors()->first())
                    ->danger()
                    ->send();

                return;
            }

            // Validate SMTP configuration format
            // Note: Actual connection testing happens when sending test email
            $testMailerName = 'test_smtp_'.time();
            config(["mail.mailers.{$testMailerName}" => [
                'transport' => 'smtp',
                'host' => $emailData['smtp_host'],
                'port' => (int) ($emailData['smtp_port'] ?? 587),
                'encryption' => $emailData['smtp_encryption'] ?? 'tls',
                'username' => $emailData['smtp_username'] ?? null,
                'password' => $emailData['smtp_password'] ?? null,
                'timeout' => 10,
            ]]);

            // Create mailer instance to validate configuration format
            Mail::mailer($testMailerName);

            Notification::make()
                ->title('SMTP Configuration Valid')
                ->body('SMTP configuration is valid. Use "Send Test Email" to verify connection.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('SMTP Connection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function sendNotification(string $title, ?string $message = null): void
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->success()
            ->send();
    }

    /**
     * Get the header actions for the page.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_smtp')
                ->label('Test SMTP Connection')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->action('testSmtpConnection'),
            Action::make('send_test_email')
                ->label('Send Test Email')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->action('sendTestEmail'),
        ];
    }

    /**
     * Get the actions for the page.
     *
     * @return array<int, Action>
     */
    protected function getActions(): array
    {
        return [
            Action::make('createTemplate')
                ->label('Create Email Template')
                ->icon('heroicon-o-plus')
                ->form([
                    TextInput::make('name')
                        ->label('Template Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('A descriptive name for this template'),

                    Select::make('type')
                        ->label('Template Type')
                        ->options([
                            EmailTemplate::TYPE_BUYER_QUOTE => 'Buyer Quote',
                            EmailTemplate::TYPE_BUYER_ORDER => 'Buyer Order',
                            EmailTemplate::TYPE_SUPPLIER_ORDER => 'Supplier Order',
                            EmailTemplate::TYPE_DELIVERY_ORDER => 'Delivery Order',
                        ])
                        ->required()
                        ->default(fn () => $this->createTemplateType)
                        ->disabled(fn () => $this->createTemplateType !== null)
                        ->dehydrated(),

                    Textarea::make('content')
                        ->label('Template Content')
                        ->required()
                        ->rows(5)
                        ->helperText('Use {{variable_name}} for dynamic content. Available variables: {{supplier_name}}, {{buyer_name}}, {{quote_number}}, {{order_number}}, etc.'),

                    TextInput::make('sender_email')
                        ->label('Sender Email (Optional)')
                        ->email()
                        ->helperText('Overrides global sender email for this template'),

                    TextInput::make('cc_emails')
                        ->label('CC Emails')
                        ->helperText('Comma-separated email addresses'),

                    TextInput::make('bcc_emails')
                        ->label('BCC Emails')
                        ->helperText('Comma-separated email addresses'),
                ])
                ->action(function (array $data): void {
                    $this->createTemplate($data);
                })
                ->modalWidth('2xl'),
            Action::make('editTemplate')
                ->label('Edit Email Template')
                ->icon('heroicon-o-pencil')
                ->form([
                    TextInput::make('name')
                        ->label('Template Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('A descriptive name for this template'),

                    Select::make('type')
                        ->label('Template Type')
                        ->options([
                            EmailTemplate::TYPE_BUYER_QUOTE => 'Buyer Quote',
                            EmailTemplate::TYPE_BUYER_ORDER => 'Buyer Order',
                            EmailTemplate::TYPE_SUPPLIER_ORDER => 'Supplier Order',
                            EmailTemplate::TYPE_DELIVERY_ORDER => 'Delivery Order',
                        ])
                        ->required()
                        ->disabled()
                        ->dehydrated(),

                    Textarea::make('content')
                        ->label('Template Content')
                        ->required()
                        ->rows(5)
                        ->helperText('Use {{variable_name}} for dynamic content. Available variables: {{supplier_name}}, {{buyer_name}}, {{quote_number}}, {{order_number}}, etc.'),

                    TextInput::make('sender_email')
                        ->label('Sender Email (Optional)')
                        ->email()
                        ->helperText('Overrides global sender email for this template'),

                    TextInput::make('cc_emails')
                        ->label('CC Emails')
                        ->helperText('Comma-separated email addresses'),

                    TextInput::make('bcc_emails')
                        ->label('BCC Emails')
                        ->helperText('Comma-separated email addresses'),
                ])
                ->fillForm(function (array $arguments): array {
                    $template = EmailTemplate::find($arguments['id'] ?? null);
                    if (!$template) {
                        return [];
                    }

                    return [
                        'name' => $template->name,
                        'type' => $template->type,
                        'content' => $template->content,
                        'sender_email' => $template->sender_email ?? '',
                        'cc_emails' => $template->cc_emails ? implode(', ', $template->cc_emails) : '',
                        'bcc_emails' => $template->bcc_emails ? implode(', ', $template->bcc_emails) : '',
                    ];
                })
                ->action(function (array $data, array $arguments): void {
                    $this->updateTemplate($arguments['id'] ?? null, $data);
                })
                ->modalWidth('2xl'),
        ];
    }

    /**
     * Update an email template.
     */
    public function updateTemplate(?int $templateId, array $data): void
    {
        if (!$templateId) {
            Notification::make()
                ->title('Template ID Required')
                ->body('Template ID is required to update a template.')
                ->danger()
                ->send();
            return;
        }

        /** @var Team $team */
        $team = Filament::getTenant();
        $template = EmailTemplate::find($templateId);

        if (!$template) {
            Notification::make()
                ->title('Template Not Found')
                ->body('The template you are trying to edit does not exist.')
                ->danger()
                ->send();
            return;
        }

        if ($template->team_id !== $team->id) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You can only edit templates belonging to your team.')
                ->danger()
                ->send();
            return;
        }

        $emailService = app(EmailTemplateService::class);

        $template->update([
            'name' => $data['name'],
            'content' => $data['content'],
            'sender_email' => !empty($data['sender_email']) ? $data['sender_email'] : null,
            'cc_emails' => !empty($data['cc_emails']) ? $emailService->parseEmailList($data['cc_emails']) : null,
            'bcc_emails' => !empty($data['bcc_emails']) ? $emailService->parseEmailList($data['bcc_emails']) : null,
        ]);

        Notification::make()
            ->title('Template Updated')
            ->body("Template '{$template->name}' has been updated successfully.")
            ->success()
            ->send();

        // Refresh the form
        $this->mount();
    }

    /**
     * Load default template content for the create template modal.
     * This method is called from the createOptionForm modal.
     */
    public function loadDefaultTemplateForCreate(string $type): void
    {
        // Map template types to Blade file paths
        $templateFileMap = [
            EmailTemplate::TYPE_BUYER_QUOTE => 'emails/quote-to-buyer.blade.php',
            EmailTemplate::TYPE_BUYER_ORDER => 'emails/buyer-order-to-buyer.blade.php',
            EmailTemplate::TYPE_SUPPLIER_ORDER => 'emails/purchase-order-to-supplier.blade.php',
            EmailTemplate::TYPE_DELIVERY_ORDER => 'emails/shipment-to-buyer.blade.php',
        ];

        $bladeFilePath = $templateFileMap[$type] ?? null;

        if (!$bladeFilePath) {
            Notification::make()
                ->title('Invalid Template Type')
                ->body('No default template file found for this type.')
                ->warning()
                ->send();
            return;
        }

        // Read the Blade file content
        $fullPath = resource_path("views/{$bladeFilePath}");
        
        if (!file_exists($fullPath)) {
            Notification::make()
                ->title('Default Template Not Found')
                ->body("Template file not found: {$bladeFilePath}")
                ->warning()
                ->send();
            return;
        }

        $content = file_get_contents($fullPath);

        if ($content === false || empty(trim($content))) {
            Notification::make()
                ->title('Default Template Empty')
                ->body('The default template file is empty.')
                ->warning()
                ->send();
            return;
        }

        // Store the content in a public property that can be accessed by the form
        $this->createTemplateType = $type;
        
        // Dispatch a browser event with the content to update the modal form
        $this->dispatch('load-default-template-content', content: $content);
        
        // Also try to update via Livewire's form state if possible
        // Note: This might not work in modal context, but worth trying
        try {
            if (method_exists($this, 'form') && $this->form) {
                // Try to get the form and update it
                $this->form->fill(['content' => $content]);
            }
        } catch (\Exception $e) {
            // Ignore if form is not available in this context
        }
        
        Notification::make()
            ->title('Default Template Loaded')
            ->body('Default template content has been loaded.')
            ->success()
            ->send();
    }

}
