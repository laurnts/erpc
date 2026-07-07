<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerQuote;
use App\Models\EmailTemplate;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class QuoteToBuyerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BuyerQuote $quote
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();

        // Get template using new system (template ID) with fallback to old system
        $template = null;
        if (isset($settings->email_template_buyer_quote_id) && $settings->email_template_buyer_quote_id) {
            $template = $emailService->getTemplateForSending(
                $settings->email_template_buyer_quote_id,
                EmailTemplate::TYPE_BUYER_QUOTE,
                $this->quote->team
            );
        }

        $fromAddress = $template
            ? $emailService->getSenderEmailFromTemplate($template, $settings)
            : $emailService->getSenderEmail($settings->email_template_buyer_quote ?? null, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Quote '.$this->quote->quote_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        // Ensure items are loaded for the email template
        if (! $this->quote->relationLoaded('items')) {
            $this->quote->load(['items.requestItem']);
        } else {
            $this->quote->loadMissing('items.requestItem');
        }

        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();

        // Get template using new system (template ID) with fallback to default template
        $templateId = $settings->email_template_buyer_quote_id ?? null;
        $template = null;

        if ($templateId) {
            // Use selected template
            $template = $emailService->getTemplateForSending(
                $templateId,
                EmailTemplate::TYPE_BUYER_QUOTE,
                $this->quote->team
            );
        } else {
            // If no template ID is set, use default template
            $template = $emailService->getDefaultTemplate(EmailTemplate::TYPE_BUYER_QUOTE);
        }

        $currency = $this->quote->currency ?? null;
        $totalAmount = $currency
            ? $currency->format((float) $this->quote->total)
            : number_format((float) $this->quote->total, 2);

        $variables = [
            'buyer_name' => $this->quote->buyer->name ?? 'Buyer',
            'quote_number' => $this->quote->quote_number,
            'request_number' => $this->quote->request->request_number ?? '',
            'valid_until' => $this->quote->valid_until?->format('M j, Y') ?? '',
            'total_amount' => $totalAmount,
        ];

        // Use template content if available, otherwise fallback to old system for backward compatibility
        $content = '';
        $isFullHtml = false;

        if ($template) {
            $result = $emailService->renderTemplateContent($template, $variables);
            $content = $result['content'];
            $isFullHtml = $result['is_full_html'];
        }

        // Fallback to old system only if template content is empty
        if (empty($content) && $settings->email_template_buyer_quote) {
            $content = $emailService->renderTemplate($settings->email_template_buyer_quote, $variables);
        }

        // If template is full HTML, render it as Blade template with all necessary variables
        if ($isFullHtml && ! empty($content)) {
            try {
                $content = $this->normalizeFullHtmlItemsTable($content);

                $renderedContent = \Illuminate\Support\Facades\Blade::render($content, [
                    'quote' => $this->quote,
                    'team' => $this->quote->team,
                    'buyer' => $this->quote->buyer,
                    'request' => $this->quote->request,
                    'currency' => $currency,
                    'totalAmount' => $totalAmount,
                    'content' => '',
                ]);

                return new Content(
                    htmlString: $renderedContent,
                );
            } catch (\Exception $e) {
                \Log::error('Failed to render Blade template from database', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
                // Fall back to default blade template on error
            }
        }

        return new Content(
            view: 'emails.quote-to-buyer',
            with: [
                'quote' => $this->quote,
                'content' => $content,
                'team' => $this->quote->team,
            ],
        );
    }

    /**
     * Replace legacy flat item loops in stored full-HTML templates with the shared
     * hierarchical items partial used by the default buyer quote email.
     */
    private function normalizeFullHtmlItemsTable(string $content): string
    {
        if (! str_contains($content, '@foreach($quote->items as $index => $item)')) {
            return $content;
        }

        $updated = preg_replace(
            '/@if\(\$quote->items && \$quote->items->count\(\) > 0\)\s*(?:<!-- Items Table -->)?.*?<\/table>\s*@endif/s',
            "@include('emails.partials.buyer-quote-items-table', ['quote' => \$quote])",
            $content,
            1,
        );

        return is_string($updated) ? $updated : $content;
    }
}
