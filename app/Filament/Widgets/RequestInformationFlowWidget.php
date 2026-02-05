<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;

final class RequestInformationFlowWidget extends Widget
{
    protected string $view = 'filament.widgets.request-information-flow-widget';

    protected int | string | array $columnSpan = 'full';

    // Make widget poll for updates to detect tab changes
    protected ?string $pollingInterval = null;

    #[Computed]
    public function getInformationFlowText(): string
    {
        // Get the active relation manager from the URL or Livewire state
        $activeRelationManager = null;
        
        // Method 1: Try to get from URL query parameter (most reliable)
        $activeRelationManager = request()->query('activeRelationManager');
        
        // Method 2: Try to get from Livewire's current component
        if ($activeRelationManager === null) {
            try {
                $livewire = \Livewire\Livewire::current();
                if ($livewire && property_exists($livewire, 'activeRelationManager')) {
                    $activeRelationManager = $livewire->activeRelationManager;
                }
            } catch (\Exception $e) {
                // Continue
            }
        }
        
        // Method 3: Try to get from JavaScript/Alpine.js state via DOM
        // We'll use JavaScript to pass it, but for now try URL parsing
        if ($activeRelationManager === null) {
            // Check if there's a tab with aria-selected="true"
            // This will be handled by JavaScript
        }
        
        if ($activeRelationManager === null) {
            return '';
        }

        // Map relation manager index to key
        $relationKey = $this->getRelationManagerKeyFromIndex($activeRelationManager);

        return match ($relationKey) {
            'items' => $this->getItemsInformationFlow(),
            'supplierQuotes' => $this->getSupplierQuotesInformationFlow(),
            'buyerQuotes' => $this->getBuyerQuotesInformationFlow(),
            'buyerOrders' => $this->getBuyerOrdersInformationFlow(),
            'supplierOrders' => $this->getSupplierOrdersInformationFlow(),
            'invoices' => $this->getBuyerOrdersInformationFlow(),
            'purchases' => $this->getSupplierOrdersInformationFlow(),
            'shipments' => $this->getShipmentsInformationFlow(),
            default => '',
        };
    }

    /**
     * Convert a relation manager index to its key.
     */
    private function getRelationManagerKeyFromIndex(int|string $index): ?string
    {
        $map = [
            'items' => 0,
            'supplierQuotes' => 1,
            'buyerQuotes' => 2,
            'invoices' => 3,
            'purchases' => 4,
            'shipments' => 5,
        ];

        // If it's already a string key, return it
        if (is_string($index) && ! is_numeric($index)) {
            return $index;
        }

        $index = (int) $index;
        $keys = array_keys($map);

        return $keys[$index] ?? null;
    }

    /**
     * Get information flow text for Requested Items tab.
     */
    public function getItemsInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 1: Requested Items**

1. **Add Items**: Click "+ New item" to add items requested by the buyer
2. **Match to Articles**: For each item, select or create an article to match it with your product catalog
3. **Set Quantities**: Enter the quantity and unit of measure for each item
4. **Complete Matching**: Ensure all items are matched (green checkmark) before proceeding to Supplier Quotes

**Note**: All items must be matched to articles before you can request quotes from suppliers.
MARKDOWN;
    }

    /**
     * Get information flow text for Supplier Quotes tab.
     */
    public function getSupplierQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 2: Supplier Quotes**

1. **Generate Quotes**: Click "Generate Supplier Quotes" to automatically create quote requests for all suppliers
2. **Input Supplier Prices**: Click "Input Supplier Price" on each quote to enter supplier pricing
3. **Status Updates**: Quote status automatically changes from "Pending" to "Received" when prices are entered
4. **Select Best Quote**: Review all quotes and select the best one(s) for each item
5. **Compare Quotes**: Use "Compare Quotes" button to view all supplier quotes side by side

**Note**: Only selected supplier quotes will be used when creating buyer quotes.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Quotes tab.
     */
    public function getBuyerQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 3: Buyer Quotes**

1. **Create Buyer Quote**: Click "+ New buyer quote" to create a quote for the buyer
2. **Set Pricing**: Review and adjust selling prices, margins, and tax settings
3. **Create PNL**: Click "Create PNL" to generate a Profit & Loss document for approval
4. **Send Quote**: After PNL is created, click "Send" to email the quote to the buyer
5. **Upload PO**: Once buyer sends Purchase Order, click "Upload PO" to upload the PO file
6. **Status Updates**: Quote status automatically changes to "Accepted" when PO is uploaded

**Note**: You must create a PNL before you can send the quote to the buyer.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Orders tab.
     */
    public function getBuyerOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 4: Buyer Orders**

1. **Create Order**: Click "Create from Quote" to convert an accepted buyer quote into an order
2. **Review Order**: Verify all order details, pricing, and payment terms
3. **Confirm Order**: Once confirmed, the order status changes to "Confirmed"
4. **Track Status**: Monitor order status through the workflow stages

**Note**: Only accepted buyer quotes can be converted to orders.
MARKDOWN;
    }

    /**
     * Get information flow text for Supplier Orders tab.
     */
    public function getSupplierOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 5: Supplier Orders**

1. **Create Order**: Click "Create from Quote" to convert a selected supplier quote into a purchase order
2. **Review Order**: Verify all order details, pricing, and terms
3. **Send to Supplier**: Send the purchase order to the supplier
4. **Track Status**: Monitor order status and supplier confirmations

**Note**: Only selected supplier quotes can be converted to purchase orders.
MARKDOWN;
    }

    /**
     * Get information flow text for Invoices tab.
     */
    public function getInvoicesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 5: Invoices**

1. **Create Invoice**: Generate invoices for buyer orders that have been confirmed
2. **Review Invoice**: Verify all invoice details, line items, and amounts
3. **Send Invoice**: Send the invoice to the buyer for payment
4. **Track Payment**: Monitor invoice status and payment confirmations
5. **Record Payment**: Mark invoice as paid when payment is received

**Note**: Invoices are generated from confirmed buyer orders and track payment status.
MARKDOWN;
    }

    /**
     * Get information flow text for Purchases tab.
     */
    public function getPurchasesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 6: Purchases**

1. **View Purchases**: Review all purchase orders created from supplier quotes
2. **Track Status**: Monitor purchase order status and supplier confirmations
3. **Receive Goods**: Update purchase status when goods are received
4. **Match Invoices**: Link supplier invoices to purchase orders
5. **Complete Purchase**: Mark purchase as complete when all items are received and invoiced

**Note**: Purchases track the procurement process from order creation to receipt of goods.
MARKDOWN;
    }

    /**
     * Get information flow text for Shipments tab.
     */
    public function getShipmentsInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 7: Inbound Shipments**

1. **Create Shipment**: Create a new shipment record when goods are received
2. **Link Orders**: Associate the shipment with relevant supplier orders
3. **Track Delivery**: Update shipment status as goods are received and inspected
4. **Complete Shipment**: Mark shipment as delivered when all items are received

**Note**: Shipments help track the physical receipt of goods from suppliers.
MARKDOWN;
    }
}
