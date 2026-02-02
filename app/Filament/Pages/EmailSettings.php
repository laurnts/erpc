<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Data\TeamErpSettings;
use App\Mail\TestEmailMail;
use App\Models\Team;
use App\Services\Email\EmailTemplateService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
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

/**
 * @property Schema $emailForm
 */
final class EmailSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use WithRateLimiting;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 16;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

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
        $smtpPassword = $settings->smtp_password ? Crypt::decryptString($settings->smtp_password) : null;

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
            'email_template_buyer_quote_content' => $settings->email_template_buyer_quote['content'] ?? null,
            'email_template_buyer_quote_sender' => $settings->email_template_buyer_quote['sender_email'] ?? '',
            'email_template_buyer_quote_cc' => isset($settings->email_template_buyer_quote['cc_emails']) ? implode(', ', $settings->email_template_buyer_quote['cc_emails']) : '',
            'email_template_buyer_quote_bcc' => isset($settings->email_template_buyer_quote['bcc_emails']) ? implode(', ', $settings->email_template_buyer_quote['bcc_emails']) : '',
            'email_template_buyer_order_content' => $settings->email_template_buyer_order['content'] ?? null,
            'email_template_buyer_order_sender' => $settings->email_template_buyer_order['sender_email'] ?? '',
            'email_template_buyer_order_cc' => isset($settings->email_template_buyer_order['cc_emails']) ? implode(', ', $settings->email_template_buyer_order['cc_emails']) : '',
            'email_template_buyer_order_bcc' => isset($settings->email_template_buyer_order['bcc_emails']) ? implode(', ', $settings->email_template_buyer_order['bcc_emails']) : '',
            'email_template_supplier_order_content' => $settings->email_template_supplier_order['content'] ?? null,
            'email_template_supplier_order_sender' => $settings->email_template_supplier_order['sender_email'] ?? '',
            'email_template_supplier_order_cc' => isset($settings->email_template_supplier_order['cc_emails']) ? implode(', ', $settings->email_template_supplier_order['cc_emails']) : '',
            'email_template_supplier_order_bcc' => isset($settings->email_template_supplier_order['bcc_emails']) ? implode(', ', $settings->email_template_supplier_order['bcc_emails']) : '',
            'email_template_delivery_order_content' => $settings->email_template_delivery_order['content'] ?? null,
            'email_template_delivery_order_sender' => $settings->email_template_delivery_order['sender_email'] ?? '',
            'email_template_delivery_order_cc' => isset($settings->email_template_delivery_order['cc_emails']) ? implode(', ', $settings->email_template_delivery_order['cc_emails']) : '',
            'email_template_delivery_order_bcc' => isset($settings->email_template_delivery_order['bcc_emails']) ? implode(', ', $settings->email_template_delivery_order['bcc_emails']) : '',
        ]);
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
                            ->visible(function () {
                                /** @var Team $team */
                                $team = Filament::getTenant();
                                $settings = $team->getErpSettings();
                                return !empty($settings->email_logo_media_id);
                            }),
                        
                        FileUpload::make('email_logo')
                            ->label('Upload New Logo')
                            ->image()
                            ->disk('public')
                            ->directory('email-logos')
                            ->helperText('Upload a new logo to replace the current one. Logo displayed at the top of email templates.')
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state) {
                                // Save logo immediately when uploaded
                                if ($state && (is_array($state) ? !empty($state) : !empty($state))) {
                                    /** @var Team $team */
                                    $team = Filament::getTenant();
                                    $currentSettings = $team->getErpSettings();
                                    
                                    $filePath = is_array($state) ? $state[0] : $state;
                                    if (is_string($filePath)) {
                                        $fullPath = storage_path('app/public/'.ltrim($filePath, '/'));
                                        
                                        if (file_exists($fullPath)) {
                                            // Delete old logo if exists
                                            if ($currentSettings->email_logo_media_id) {
                                                $oldMedia = $team->getMedia('email_logo')
                                                    ->firstWhere('id', $currentSettings->email_logo_media_id);
                                                if ($oldMedia) {
                                                    $oldMedia->delete();
                                                }
                                            }
                                            
                                            // Add new logo
                                            $media = $team->addMedia($fullPath)
                                                ->toMediaCollection('email_logo');
                                            
                                            // Update settings immediately
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
                                                email_from_address: $currentSettings->email_from_address,
                                                email_from_name: $currentSettings->email_from_name,
                                                email_logo_media_id: (string) $media->id,
                                                email_signature: $currentSettings->email_signature,
                                                test_email_address: $currentSettings->test_email_address,
                                                smtp_host: $currentSettings->smtp_host,
                                                smtp_port: $currentSettings->smtp_port,
                                                smtp_username: $currentSettings->smtp_username,
                                                smtp_password: $currentSettings->smtp_password,
                                                smtp_encryption: $currentSettings->smtp_encryption,
                                                email_template_buyer_quote: $currentSettings->email_template_buyer_quote,
                                                email_template_buyer_order: $currentSettings->email_template_buyer_order,
                                                email_template_supplier_order: $currentSettings->email_template_supplier_order,
                                                email_template_delivery_order: $currentSettings->email_template_delivery_order,
                                            );
                                            
                                            $team->erp_settings = $settings;
                                            $team->save();
                                            
                                            // Refresh team to ensure latest data
                                            $team->refresh();
                                            
                                            // Notify user
                                            Notification::make()
                                                ->title('Logo Uploaded')
                                                ->body('Email logo has been uploaded successfully.')
                                                ->success()
                                                ->send();
                                        }
                                    }
                                }
                            }),

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
                    ->columns(1),

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
     * Build a template section with content, sender, CC, and BCC fields.
     */
    private function buildTemplateSection(string $key, string $label, string $helperText): Section
    {
        return Section::make($label)
            ->schema([
                Textarea::make("email_template_{$key}_content")
                    ->label('Template Content')
                    ->rows(5)
                    ->helperText($helperText . ' Leave empty to use the default template from the system.')
                    ->placeholder('Leave empty to use the default template with items table...'),

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

        // Build template configurations
        $templates = [
            'buyer_quote',
            'buyer_order',
            'supplier_order',
            'delivery_order',
        ];

        $templateConfigs = [];
        foreach ($templates as $template) {
            $content = trim($emailData["email_template_{$template}_content"] ?? '');
            $sender = !empty($emailData["email_template_{$template}_sender"]) ? trim($emailData["email_template_{$template}_sender"]) : null;
            $cc = trim($emailData["email_template_{$template}_cc"] ?? '');
            $bcc = trim($emailData["email_template_{$template}_bcc"] ?? '');

            // Always save template config, even if empty (to allow clearing templates)
            $templateConfigs["email_template_{$template}"] = [
                'content' => $content,
                'sender_email' => $sender,
                'cc_emails' => ! empty($cc) ? $emailService->parseEmailList($cc) : [],
                'bcc_emails' => ! empty($bcc) ? $emailService->parseEmailList($bcc) : [],
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

}
