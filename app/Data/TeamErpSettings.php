<?php

declare(strict_types=1);

namespace App\Data;

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
    ) {}
}
