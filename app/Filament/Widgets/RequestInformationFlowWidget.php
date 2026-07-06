<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;

final class RequestInformationFlowWidget extends Widget
{
    protected string $view = 'filament.widgets.request-information-flow-widget';

    protected int|string|array $columnSpan = 'full';

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
            'fulfillment' => $this->getFulfillmentInformationFlow(),
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
            'fulfillment' => 6,
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

Add the buyer's requested items and send them out for supplier quotes.

- Add the items requested by the buyer to this request.
- Match each item to an article (required before requesting supplier quotes).
- Use **Send to all suppliers** or **Send to supplier** to request quotes.

**Next:** once sent, continue to Supplier Quotes.
MARKDOWN;
    }

    /**
     * Get information flow text for Supplier Quotes tab.
     */
    public function getSupplierQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 2: Supplier Quotes**

Collect supplier prices and select the best option per item.

- Enter or upload supplier prices for the requested items.
- Review and compare quotes; select the best option(s) per item.
- Create a Quotation Evaluation (QE) when required; it must be approved before Buyer Quotes.
- Use **Send to buyer** to send the selected quotes to the buyer.

**Next:** once sent, continue to Buyer Quotes.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Quotes tab.
     */
    public function getBuyerQuotesInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 3: Buyer Quotes**

Build the buyer quote, set pricing, and get it accepted.

- Create the buyer quote from the selected supplier-quote items.
- Set selling prices, margins, and tax; verify details and terms.
- Create a Profit & Loss (P&L) document; it must be approved before Invoices and later stages.
- Use **Send** to send the quote to the buyer.
- When the buyer sends a PO, use **Upload PO** so the status becomes **Accepted**.

**Next:** after at least one quote is Accepted, continue to Supplier Orders.
MARKDOWN;
    }

    /**
     * Get information flow text for Buyer Orders (Invoices) tab.
     */
    public function getBuyerOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 6: Invoices (Buyer Orders)**

Invoice the buyer from the accepted quote(s).

- Available once P&L is approved and at least one buyer quote is Accepted.
- Buyer orders are created from accepted buyer quote(s) and act as the invoice to the buyer.
- Open the buyer order and review items, pricing, and terms.
- Use **Confirm** to confirm the order (this can create or link to supplier orders).
- Use **Send** to send the order to the buyer when ready.

**Next:** once sent, invoicing is complete; shipments proceed once Goods Receive is approved.
MARKDOWN;
    }

    /**
     * Get information flow text for Supplier Orders tab.
     */
    public function getSupplierOrdersInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 4: Supplier Orders**

Create purchase orders to suppliers, get them approved, and send them.

- Create purchase orders from accepted buyer quote(s) — there may be one PO per supplier. Verify quantities, prices, and terms, then **Confirm** each order to request approval.
- Get each order approved: 2 approvals required (Dept Head of Sales, Deputy Director, or Director), via the **Approve** action or the Approval menu.
- Use **Send PO to Supplier** to email each approved PO to its supplier.

**Next:** Goods Receive unlocks once every order is approved and sent.
MARKDOWN;
    }

    /**
     * Get information flow text for Goods Receive tab.
     */
    public function getGoodsReceiveInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 5: Goods Receive**

Receive the delivered goods and approve the paperwork.

- Available only after all supplier orders are approved and sent.
- Upload goods receive documents (delivery notes, packing lists, etc.); multiple files are supported.
- All documents must be **approved** via Approval > Goods Receive.

**Next:** approved documents unlock Fulfillment (goods shipments).
MARKDOWN;
    }

    /**
     * Get information flow text for the Fulfillment tab.
     */
    public function getFulfillmentInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 7: Fulfillment**

Record fulfillment for each channel of this request:

- **Goods** — create inbound shipments and mark them delivered.
- **Services** — file acceptance reports for completed service items.

For a mixed request, both channels appear here. Goods require approved Goods Receive documents first.
MARKDOWN;
    }

    /**
     * Get information flow text for Completion Report tab.
     */
    public function getCompletionReportsInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 8: Completion Report**

Document project completion after delivery.

- Available after shipments are delivered.
- Upload completion report documentation (delivery confirmations, inspection reports, certificates, or other completion documents).
- Documents are stored securely and can be downloaded or viewed at any time.

**Next:** the request is complete once the report is filed.
MARKDOWN;
    }
}
