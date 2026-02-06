<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

final class TeamErpSettings extends Data
{
    public function __construct(
        // Company Info
        #[Max(255)]
        public string $company_name = '',
        #[Max(500)]
        public string $company_address = '',
        #[Max(50)]
        public string $company_phone = '',
        #[Max(255)]
        public string $company_email = '',

        // Default Settings
        #[Max(3)]
        public string $default_currency = 'USD',
        #[Min(0), Max(100)]
        public float $default_tax_percent = 11.0,
        #[Min(1), Max(365)]
        public int $quote_validity_days = 30,
        #[Min(0), Max(365)]
        public int $default_payment_terms_days = 30,
        public bool $prices_include_tax = false,
        #[Min(0), Max(100)]
        public float $default_margin_percent = 3.0,

        // Document Number Prefixes
        #[Max(10)]
        public string $request_number_prefix = 'REQ',
        #[Max(10)]
        public string $project_number_prefix = 'PRJ',
        #[Max(10)]
        public string $buyer_quote_number_prefix = 'BQ',
        #[Max(10)]
        public string $buyer_order_number_prefix = 'BO',
        #[Max(10)]
        public string $supplier_order_number_prefix = 'PO',
        #[Max(10)]
        public string $shipment_number_prefix = 'SHP',
        #[Max(10)]
        public string $buyer_invoice_number_prefix = 'INV',
        #[Max(10)]
        public string $supplier_invoice_number_prefix = 'SI',
        #[Max(10)]
        public string $buyer_payment_number_prefix = 'PAY',
        #[Max(10)]
        public string $supplier_payment_number_prefix = 'SP',

        // Email Configuration
        #[Email, Max(255)]
        public string $email_from_address = '',
        #[Max(255)]
        public string $email_from_name = '',
        public ?string $email_logo_media_id = null,
        public string $email_signature = '',
        #[Email, Max(255)]
        public string $test_email_address = '',

        // SMTP Configuration
        #[Max(255)]
        public ?string $smtp_host = null,
        #[Min(1), Max(65535)]
        public ?int $smtp_port = null,
        #[Max(255)]
        public ?string $smtp_username = null,
        public ?string $smtp_password = null, // Encrypted
        #[Max(10)]
        public ?string $smtp_encryption = null, // 'tls', 'ssl', or null

        // Email Templates - New system (template IDs)
        public ?int $email_template_buyer_quote_id = null,
        public ?int $email_template_buyer_order_id = null,
        public ?int $email_template_supplier_order_id = null,
        public ?int $email_template_delivery_order_id = null,

        // Email Templates - Old system (stored as arrays with content, sender_email, cc_emails, bcc_emails)
        // Kept for backward compatibility during migration
        /** @var array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null */
        public ?array $email_template_buyer_quote = null,
        /** @var array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null */
        public ?array $email_template_buyer_order = null,
        /** @var array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null */
        public ?array $email_template_supplier_order = null,
        /** @var array{content: string, sender_email?: string|null, cc_emails?: string[], bcc_emails?: string[]}|null */
        public ?array $email_template_delivery_order = null,
    ) {}
}
