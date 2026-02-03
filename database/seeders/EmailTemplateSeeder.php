<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

final class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default templates extracted from blade files (@else block content)
        // These match the default content shown when $content is empty in the blade templates
        $defaults = [
            [
                'type' => EmailTemplate::TYPE_BUYER_QUOTE,
                'name' => 'Default Buyer Quote',
                // From quote-to-buyer.blade.php @else block
                'content' => "Dear {{buyer_name}},\n\nRegarding with the request for quotation, below we provide the following item details.",
                'sender_email' => null,
                'cc_emails' => null,
                'bcc_emails' => null,
                'is_default' => true,
            ],
            [
                'type' => EmailTemplate::TYPE_BUYER_ORDER,
                'name' => 'Default Buyer Order',
                // From buyer-order-to-buyer.blade.php @else block
                'content' => "Dear {{buyer_name}},\n\nPlease find below the details of your order.",
                'sender_email' => null,
                'cc_emails' => null,
                'bcc_emails' => null,
                'is_default' => true,
            ],
            [
                'type' => EmailTemplate::TYPE_SUPPLIER_ORDER,
                'name' => 'Default Purchase Order',
                // From purchase-order-to-supplier.blade.php @else block
                'content' => "Dear {{supplier_name}},\n\nPlease find below the details of our purchase order.",
                'sender_email' => null,
                'cc_emails' => null,
                'bcc_emails' => null,
                'is_default' => true,
            ],
            [
                'type' => EmailTemplate::TYPE_DELIVERY_ORDER,
                'name' => 'Default Delivery Order',
                // From shipment-to-buyer.blade.php @else block
                'content' => "Dear {{buyer_name}},\n\nPlease find below the delivery order details for your shipment.",
                'sender_email' => null,
                'cc_emails' => null,
                'bcc_emails' => null,
                'is_default' => true,
            ],
        ];

        foreach ($defaults as $default) {
            EmailTemplate::firstOrCreate(
                [
                    'team_id' => null,
                    'type' => $default['type'],
                    'name' => $default['name'],
                ],
                $default
            );
        }
    }
}
