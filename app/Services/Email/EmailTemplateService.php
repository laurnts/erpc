<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Data\TeamErpSettings;
use App\Models\EmailTemplate;
use App\Models\Team;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

final readonly class EmailTemplateService
{
    /**
     * Get team email settings with fallbacks to global config.
     */
    public function getTeamEmailSettings(Team $team): TeamErpSettings
    {
        return $team->getErpSettings();
    }

    /**
     * Get template by ID with fallback to default.
     */
    public function getTemplate(?int $templateId, string $type, ?Team $team = null): ?EmailTemplate
    {
        if ($templateId) {
            $template = EmailTemplate::find($templateId);
            if ($template && $template->type === $type) {
                // Check if template is accessible to team (belongs to team or is default)
                // For non-default templates, must belong to the team
                if ($team && ! $template->is_default && $template->team_id !== $team->id) {
                    \Log::warning('Template does not belong to team, falling back to default', [
                        'template_id' => $templateId,
                        'template_team_id' => $template->team_id,
                        'requested_team_id' => $team->id,
                        'type' => $type,
                    ]);

                    return $this->getDefaultTemplate($type);
                }

                return $template;
            }
        }

        return $this->getDefaultTemplate($type);
    }

    /**
     * Get default template for a type.
     */
    public function getDefaultTemplate(string $type): ?EmailTemplate
    {
        return EmailTemplate::defaults()
            ->forType($type)
            ->first();
    }

    /**
     * Get template for sending (with fallback to default).
     */
    public function getTemplateForSending(?int $templateId, string $type, ?Team $team = null): ?EmailTemplate
    {
        $template = $this->getTemplate($templateId, $type, $team);

        // If template was deleted but ID still exists, fallback to default
        if ($templateId && ! $template) {
            \Log::warning('Selected template not found, falling back to default', [
                'template_id' => $templateId,
                'type' => $type,
            ]);

            return $this->getDefaultTemplate($type);
        }

        return $template;
    }

    /**
     * Render email template content with variable replacement.
     *
     * @param  array<string, string>  $variables
     * @return array{content: string, is_full_html: bool}
     */
    public function renderTemplateContent(?EmailTemplate $template, array $variables): array
    {
        if (! $template || empty($template->content) || trim($template->content) === '') {
            return ['content' => '', 'is_full_html' => false];
        }

        $content = trim($template->content);

        // Check if this is a full HTML email template (contains DOCTYPE or html/body tags)
        $isFullHtml = preg_match('/<!DOCTYPE\s+html|<html|<body/i', $content) === 1;

        // Replace variables in the template
        foreach ($variables as $key => $value) {
            // Support both {{{variable}}} and {{variable}} syntax
            $content = str_replace("{{{$key}}}", (string) $value, $content);
            $content = str_replace("{{$key}}", (string) $value, $content);
        }

        return ['content' => $content, 'is_full_html' => $isFullHtml];
    }

    /**
     * Get template-specific sender email from EmailTemplate or fallback to global.
     */
    public function getSenderEmailFromTemplate(?EmailTemplate $template, TeamErpSettings $settings): ?string
    {
        if ($template && ! empty($template->sender_email)) {
            return $template->sender_email;
        }

        return $settings->email_from_address === '' || $settings->email_from_address === '0' ? config('mail.from.address') : $settings->email_from_address;
    }

    /**
     * Get template-specific CC emails from EmailTemplate.
     *
     * @return array<string>
     */
    public function getCcEmailsFromTemplate(?EmailTemplate $template): array
    {
        if (! $template || empty($template->cc_emails)) {
            return [];
        }

        return is_array($template->cc_emails) ? $template->cc_emails : [];
    }

    /**
     * Get template-specific BCC emails from EmailTemplate.
     *
     * @return array<string>
     */
    public function getBccEmailsFromTemplate(?EmailTemplate $template): array
    {
        if (! $template || empty($template->bcc_emails)) {
            return [];
        }

        return is_array($template->bcc_emails) ? $template->bcc_emails : [];
    }

    /**
     * Render email template with variable replacement.
     *
     * @param  array<string, string>  $variables
     */
    public function renderTemplate(?array $templateConfig, array $variables): string
    {
        if (! $templateConfig || empty($templateConfig['content']) || trim((string) $templateConfig['content']) === '') {
            return '';
        }

        $content = trim((string) $templateConfig['content']);

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

        return $settings->email_from_address === '' || $settings->email_from_address === '0' ? config('mail.from.address') : $settings->email_from_address;
    }

    /**
     * Get template-specific sender name or fallback to global.
     */
    public function getSenderName(TeamErpSettings $settings): string
    {
        return $settings->email_from_name === '' || $settings->email_from_name === '0' ? config('mail.from.name', '') : $settings->email_from_name;
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
        if (in_array($settings->smtp_host, [null, '', '0'], true)) {
            return null; // Use default mailer
        }

        $mailerName = 'team_smtp_'.hash('xxh128', $settings->smtp_host.$settings->smtp_port);

        // Decrypt password and trim whitespace (Gmail App Passwords should have no spaces)
        $password = null;
        if (! in_array($settings->smtp_password, [null, '', '0'], true)) {
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
            && ($settings->email_from_address !== '' && $settings->email_from_address !== '0')
            && ! str_contains(strtolower($settings->email_from_address), 'gmail.com')) {
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
        if ($emails === '' || $emails === '0') {
            return [];
        }

        return array_filter(
            array_map(trim(...), explode(',', $emails)),
            fn (string $email): bool => $email !== '' && $email !== '0' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        );
    }

    /**
     * Send email using team's email settings.
     *
     * @param  array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null  $templateConfig
     * @param  string|array<string>  $to
     * @param  int|null  $templateId  Template ID (new system)
     * @param  string|null  $templateType  Template type (new system)
     */
    public function sendWithTeamSettings(
        Team $team,
        Mailable $mailable,
        string|array $to,
        ?array $templateConfig = null,
        ?int $templateId = null,
        ?string $templateType = null
    ): void {
        $settings = $team->getErpSettings();

        // Configure mailer if team SMTP is set
        $mailer = $this->configureMailer($settings);

        // Build the mail pending instance
        $pendingMail = $mailer ? Mail::mailer($mailer)->to($to) : Mail::to($to);

        // Get CC/BCC from new template system if available, otherwise use old system
        $ccEmails = [];
        $bccEmails = [];

        if ($templateId && $templateType) {
            $template = $this->getTemplateForSending($templateId, $templateType, $team);
            if ($template instanceof \App\Models\EmailTemplate) {
                $ccEmails = $this->getCcEmailsFromTemplate($template);
                $bccEmails = $this->getBccEmailsFromTemplate($template);
            }
        } else {
            // Fallback to old system
            $ccEmails = $this->getCcEmails($templateConfig);
            $bccEmails = $this->getBccEmails($templateConfig);
        }

        if ($ccEmails !== []) {
            $pendingMail->cc($ccEmails);
            \Log::info('Email CC applied', [
                'to' => is_array($to) ? $to : [$to],
                'cc' => $ccEmails,
                'mailable' => $mailable::class,
                'template_id' => $templateId,
            ]);
        }

        if ($bccEmails !== []) {
            $pendingMail->bcc($bccEmails);
            \Log::info('Email BCC applied', [
                'to' => is_array($to) ? $to : [$to],
                'bcc' => $bccEmails,
                'mailable' => $mailable::class,
                'template_id' => $templateId,
            ]);
        }

        // Log email sending details for debugging
        \Log::info('Sending email with team settings', [
            'to' => is_array($to) ? $to : [$to],
            'cc' => $ccEmails,
            'bcc' => $bccEmails,
            'mailer' => $mailer ?? 'default',
            'mailable' => $mailable::class,
            'template_id' => $templateId,
        ]);

        // Send the email
        $pendingMail->send($mailable);

        \Log::info('Email sent successfully', [
            'to' => is_array($to) ? $to : [$to],
            'mailable' => $mailable::class,
        ]);
    }
}
