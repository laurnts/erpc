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

    /**
     * Span full width at all breakpoints so the information flow is not constrained by the footer widget grid.
     *
     * @return array<string, string|int>
     */
    public function getColumnSpan(): array
    {
        return [
            'default' => 'full',
            'sm' => 'full',
            'md' => 'full',
            'lg' => 'full',
            'xl' => 'full',
            '2xl' => 'full',
        ];
    }

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
            'supplierOrders' => $this->getSupplierOrdersInformationFlow(),
            'goodsReceive' => $this->getGoodsReceiveInformationFlow(),
            'buyerOrders' => $this->getBuyerOrdersInformationFlow(),
            'shipments' => $this->getShipmentsInformationFlow(),
            'completionReports' => $this->getCompletionReportsInformationFlow(),
            default => '',
        };
    }

    /**
     * Convert relation manager index to key. Order must match ViewRequest::getRelationManagers().
     */
    private function getRelationManagerKeyFromIndex(int|string $index): ?string
    {
        $map = [
            'items' => 0,
            'supplierQuotes' => 1,
            'buyerQuotes' => 2,
            'supplierOrders' => 3,
            'goodsReceive' => 4,
            'buyerOrders' => 5,
            'shipments' => 6,
            'completionReports' => 7,
        ];

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
- Add the items requested by the buyer to this request.
- Match each item to an article (all items must be matched before requesting supplier quotes).
- Use **Send to all suppliers** or **Send to supplier** to request quotes from suppliers.
- Once sent, the next stage is Supplier Quotes.
MARKDOWN;
    }

    /**
     * Get information flow text for Supplier Quotes tab.
     */
    public function getSupplierQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 2: Supplier Quotes**
- Enter or upload supplier prices for the requested items.
- Review and compare quotes; select the best option(s) per item.
- Create a Quotation Evaluation (QE) when required; it must be approved before moving to Buyer Quotes.
- Use **Send to buyer** to send the selected quotes to the buyer.
- Once sent, proceed to Buyer Quotes.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Quotes tab.
     */
    public function getBuyerQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 3: Buyer Quotes**
- Build the buyer quote from items selected in the supplier quote(s).
- Set selling prices, margins, and tax; verify order details and terms.
- Create a Profit & Loss (P&L) document; it must be approved before Invoices and later stages.
- **Send** the buyer quote to the buyer.
- When the buyer sends a PO, **upload the PO** to the buyer quote so its status becomes **Accepted**.
- After at least one quote is Accepted, you can continue to Invoices and Purchases.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Orders (Invoices) tab.
     */
    public function getBuyerOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 6: Invoices (Buyer Orders)**
- Buyer orders are created from accepted buyer quote(s) and act as the invoice to the buyer.
- Create or open the buyer order; review items, pricing, and terms.
- **Confirm** the buyer order (this can create or link to supplier orders / purchases).
- **Send** the order to the buyer when ready.
- P&L must be approved to access this tab; at least one buyer quote must be Accepted.
MARKDOWN;
    }

    /**
     * Get information flow text for Purchases (Supplier Orders) tab.
     */
    public function getSupplierOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 4: Purchases (Supplier Orders)**
- Create purchase orders to suppliers from accepted buyer quote(s); there may be multiple POs (one per supplier).
- Add or edit supplier orders; verify quantities, prices, and terms.
- **Confirm** each order so it can be sent for approval.
- Ensure all supplier orders are **approved** (via Approval) before you can access Goods Receive.
- **Send** the PO to the supplier when approved.
MARKDOWN;
    }

    /**
     * Get information flow text for Goods Receive tab.
     */
    public function getGoodsReceiveInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 5: Goods Receive**
- Upload goods receive documents (e.g. delivery notes, packing lists); multiple files are supported.
- All documents must be **approved** (via Approval > Goods Receive) before you can open Inbound Shipments.
- This tab is only available after all supplier orders (Purchases) are approved.
MARKDOWN;
    }

    /**
     * Get information flow text for Inbound Shipments tab.
     */
    public function getShipmentsInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 7: Inbound Shipments**
- Create one or more shipments and enter details (quantities, dates, etc.) before submitting.
- Submit the shipment; you can have multiple shipments per request depending on agreements.
- When the shipment is **In Transit**, send the delivery order email to the buyer.
- Mark the shipment as **Delivered** when it reaches the buyer.
- Goods Receive documents must be approved before you can use this tab.
MARKDOWN;
    }

    /**
     * Get information flow text for Completion Report tab.
     */
    public function getCompletionReportsInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 8: Completion Report**
- Upload completion report documentation after shipments are delivered.
- Include delivery confirmations, inspection reports, certificates, or other project completion documents as needed.
- Documents are stored securely and can be downloaded or viewed at any time.
MARKDOWN;
    }
}
