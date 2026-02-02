<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Data\TeamErpSettings;
use App\Models\Team;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

final class EmailTemplateService
{
    /**
     * Get team email settings with fallbacks to global config.
     */
    public function getTeamEmailSettings(Team $team): TeamErpSettings
    {
        return $team->getErpSettings();
    }

    /**
     * Render email template with variable replacement.
     *
     * @param  array<string, string>  $variables
     */
    public function renderTemplate(?array $templateConfig, array $variables): string
    {
        if (! $templateConfig || empty($templateConfig['content']) || trim($templateConfig['content']) === '') {
            return '';
        }

        $content = trim($templateConfig['content']);

        // Check if content matches default template content (treat as empty to use default blade template)
        $defaultContents = [
            "# Quote {{quote_number}}\n\nDear {{buyer_name}},\n\nPlease find attached Quote {{quote_number}}.\n\n**Quote Details:**\n- Quote Number: {{quote_number}}\n- Valid Until: {{valid_until}}\n- Total Amount: {{total_amount}}\n\nPlease review and let us know if you have any questions.",
            "# Order {{order_number}}\n\nDear {{buyer_name}},\n\nPlease find attached Order {{order_number}}.\n\n**Order Details:**\n- Order Number: {{order_number}}\n- Order Date: {{order_date}}\n- Total Amount: {{total_amount}}\n\nThank you for your order.",
            "# Purchase Order {{order_number}}\n\nDear {{supplier_name}},\n\nPlease find attached Purchase Order {{order_number}}.\n\n**Order Details:**\n- Order Number: {{order_number}}\n- Order Date: {{order_date}}\n- Total Amount: {{total_amount}}\n\nPlease confirm receipt and expected delivery date.",
            "# Delivery Order {{shipment_number}}\n\nDear {{buyer_name}},\n\nPlease find attached Delivery Order {{shipment_number}}.\n\n**Delivery Details:**\n- Shipment Number: {{shipment_number}}\n- Delivery Date: {{delivery_date}}\n- Tracking Number: {{tracking_number}}\n- Delivery Address: {{delivery_address}}\n\nYour order has been shipped and is on its way.",
        ];

        // If content matches any default, treat as empty to use default blade template
        if (in_array($content, $defaultContents, true)) {
            return '';
        }

        // If content looks like HTML template (contains HTML tags), treat as empty to use default blade template
        // This prevents accidentally saving the full HTML template as custom content
        if (preg_match('/<html|<body|<table|<div[^>]*style/i', $content)) {
            \Log::warning('Template content appears to be HTML template, ignoring and using default blade template', [
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 100),
            ]);
            return '';
        }

        foreach ($variables as $key => $value) {
            $content = str_replace("{{{$key}}}", (string) $value, $content);
        }

        return $content;
    }

    /**
     * Get template-specific sender email or fallback to global.
     */
    public function getSenderEmail(?array $templateConfig, TeamErpSettings $settings): ?string
    {
        if ($templateConfig && ! empty($templateConfig['sender_email'])) {
            return $templateConfig['sender_email'];
        }

        return ! empty($settings->email_from_address) ? $settings->email_from_address : config('mail.from.address');
    }

    /**
     * Get template-specific sender name or fallback to global.
     */
    public function getSenderName(TeamErpSettings $settings): string
    {
        return ! empty($settings->email_from_name) ? $settings->email_from_name : config('mail.from.name', '');
    }

    /**
     * Get template-specific CC emails.
     *
     * @return array<string>
     */
    public function getCcEmails(?array $templateConfig): array
    {
        if (! $templateConfig || empty($templateConfig['cc_emails'])) {
            return [];
        }

        return is_array($templateConfig['cc_emails']) ? $templateConfig['cc_emails'] : [];
    }

    /**
     * Get template-specific BCC emails.
     *
     * @return array<string>
     */
    public function getBccEmails(?array $templateConfig): array
    {
        if (! $templateConfig || empty($templateConfig['bcc_emails'])) {
            return [];
        }

        return is_array($templateConfig['bcc_emails']) ? $templateConfig['bcc_emails'] : [];
    }

    /**
     * Configure mailer with team SMTP settings if configured.
     */
    public function configureMailer(TeamErpSettings $settings): ?string
    {
        if (empty($settings->smtp_host)) {
            return null; // Use default mailer
        }

        $mailerName = 'team_smtp_'.md5($settings->smtp_host.$settings->smtp_port);

        // Decrypt password and trim whitespace (Gmail App Passwords should have no spaces)
        $password = null;
        if (! empty($settings->smtp_password)) {
            try {
                $password = Crypt::decryptString($settings->smtp_password);
                // Remove any whitespace that might have been accidentally added
                $password = trim($password);
            } catch (\Exception $e) {
                // If decryption fails, log error but don't break - might be using default mailer
                \Log::error('Failed to decrypt SMTP password', [
                    'error' => $e->getMessage(),
                ]);
                $password = null;
            }
        }

        $mailerConfig = [
            'transport' => 'smtp',
            'host' => $settings->smtp_host,
            'port' => $settings->smtp_port ?? 587,
            'encryption' => $settings->smtp_encryption,
            'username' => $settings->smtp_username,
            'password' => $password,
            'timeout' => null,
        ];

        config(["mail.mailers.{$mailerName}" => $mailerConfig]);

        // Warn if using Gmail SMTP with different sender email
        if (str_contains(strtolower($settings->smtp_host ?? ''), 'gmail') 
            && !empty($settings->email_from_address)
            && !str_contains(strtolower($settings->email_from_address), 'gmail.com')) {
            \Log::warning('Gmail SMTP with non-Gmail sender address - emails may be rejected or marked as spam', [
                'smtp_host' => $settings->smtp_host,
                'smtp_username' => $settings->smtp_username,
                'from_address' => $settings->email_from_address,
            ]);
        }

        return $mailerName;
    }

    /**
     * Parse comma-separated email string into array.
     *
     * @return array<string>
     */
    public function parseEmailList(string $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', $emails)),
            fn (string $email): bool => ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        );
    }

    /**
     * Send email using team's email settings.
     *
     * @param  array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null  $templateConfig
     * @param  string|array<string>  $to
     */
    public function sendWithTeamSettings(
        Team $team,
        Mailable $mailable,
        string|array $to,
        ?array $templateConfig = null
    ): void {
        $settings = $team->getErpSettings();

        // Configure mailer if team SMTP is set
        $mailer = $this->configureMailer($settings);

        // Build the mail pending instance
        $pendingMail = $mailer ? Mail::mailer($mailer)->to($to) : Mail::to($to);

        // Apply CC/BCC from template config
        $ccEmails = $this->getCcEmails($templateConfig);
        $bccEmails = $this->getBccEmails($templateConfig);

        if (! empty($ccEmails)) {
            $pendingMail->cc($ccEmails);
            \Log::info('Email CC applied', [
                'to' => is_array($to) ? $to : [$to],
                'cc' => $ccEmails,
                'mailable' => get_class($mailable),
            ]);
        }

        if (! empty($bccEmails)) {
            $pendingMail->bcc($bccEmails);
            \Log::info('Email BCC applied', [
                'to' => is_array($to) ? $to : [$to],
                'bcc' => $bccEmails,
                'mailable' => get_class($mailable),
            ]);
        }

        // Log email sending details for debugging
        \Log::info('Sending email with team settings', [
            'to' => is_array($to) ? $to : [$to],
            'cc' => $ccEmails,
            'bcc' => $bccEmails,
            'mailer' => $mailer ?? 'default',
            'mailable' => get_class($mailable),
        ]);

        // Send the email
        $pendingMail->send($mailable);
        
        \Log::info('Email sent successfully', [
            'to' => is_array($to) ? $to : [$to],
            'mailable' => get_class($mailable),
        ]);
    }
}
