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
            'completionReports' => $this->getCompletionReportsInformationFlow(),
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
            'completionReports' => 6,
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
Add items requested by the buyer to the request. All listed items must be matched to articles before you can request quotes from suppliers.
Click on Send to all suppliers or Send to supplier button to suppliers to request quotes from suppliers.
MARKDOWN;
    }

    /**
     * Get information flow text for Supplier Quotes tab.
     */
    public function getSupplierQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 2: Supplier Quotes**
Input supplier prices for the items in the request.
Review the supplier quotes and select the best one(s) for each item.
Click on send to buyer to send the quotes to the buyer.
Quotation evaluation can be created once selected item applied.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Quotes tab.
     */
    public function getBuyerQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 3: Buyer Quotes**
Buyer quote has items that selected from supplier quote. 
Set selling prices, margins, and tax settings. Verify all order details, pricing, and terms.
Create a Profit & Loss document for approval before send buyer quote to buyer.
Once receive PO from buyer, upload PO to the buyer quote. It will set buyer quote status to Accepted.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Orders tab.
     */
    public function getBuyerOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 4: Buyer Orders**
Buyer order has items that selected from buyer quote. 
Confirm the buyer order to create a supplier order (PO to supplier).
Buyer order used as invoice that will send to buyer.
MARKDOWN;
    }

    /**
     * Get information flow text for Supplier Orders tab.
     */
    public function getSupplierOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 5: Supplier Orders**
Supplier order created based on buyer order as purchase order to supplier.
There will be more than one purchase order to supplier.
Verify all order details, pricing, and terms.
MARKDOWN;
    }

    /**
     * Get information flow text for Shipments tab.
     */
    public function getShipmentsInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 7: Inbound Shipments**
Create shipment and set the detail before submit it.
The shipment can be multiple depend on shipment aggrement.
Send delivery order email to buyer once status shipment is In Transit.
MARKDOWN;
    }

    /**
     * Get information flow text for Completion Report tab.
     */
    public function getCompletionReportsInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 8: Completion Report**
Upload completion report documentation after shipments are delivered.
Documentation may include delivery confirmations, inspection reports, certificates, or other project completion documents.
All uploaded documents are stored securely and can be downloaded or viewed at any time.
MARKDOWN;
    }
}
