<?php

declare(strict_types=1);

namespace App\Services\RequestDetail;

use App\Enums\RequestStage;
use App\Models\Request;

/**
 * Builds the left-column step guide for the admin request detail page.
 */
final readonly class RequestStepGuidePresenter
{
    /**
     * @return array{
     *     eyebrow: string,
     *     title: string,
     *     summary: string,
     *     checklist: list<string>,
     *     next: string,
     *     ctaLabel: string,
     *     relationKey: string|null,
     *     visible: bool,
     * }
     */
    public function forRequest(Request $request): array
    {
        $stage = $request->stage;

        if (in_array($stage, [RequestStage::CANCELLED, RequestStage::COMPLETED, RequestStage::INVOICED, RequestStage::PAID], true)) {
            return [
                'eyebrow' => '',
                'title' => $stage->getLabel(),
                'summary' => '',
                'checklist' => [],
                'next' => '',
                'ctaLabel' => '',
                'relationKey' => null,
                'visible' => false,
            ];
        }

        $phaseStep = $stage->getPhaseStep();
        $eyebrow = $phaseStep === '✓' || $phaseStep === '-'
            ? 'Workflow'
            : 'Step '.explode('/', $phaseStep)[0].' of '.explode('/', $phaseStep)[1];

        $content = $this->contentForStage($stage);

        return [
            'eyebrow' => $eyebrow,
            'title' => $stage->getTabLabel(),
            'summary' => $content['summary'],
            'checklist' => $content['checklist'],
            'next' => $content['next'],
            'ctaLabel' => 'Go to '.strtolower($stage->getTabLabel()).' →',
            'relationKey' => $stage->getRelationManagerKey(),
            'visible' => true,
        ];
    }

    /**
     * @return array{summary: string, checklist: list<string>, next: string}
     */
    private function contentForStage(RequestStage $stage): array
    {
        return match ($stage) {
            RequestStage::DRAFT => [
                'summary' => 'Add the buyer\'s requested items and send them out for supplier quotes.',
                'checklist' => [
                    'Add the items requested by the buyer to this request.',
                    'Match each item to an article (required before requesting supplier quotes).',
                    'Use Send to all suppliers or Send to supplier to request quotes.',
                ],
                'next' => 'Once sent, continue to Supplier Quotes.',
            ],
            RequestStage::AWAITING_SUPPLIER_RESPONSE => [
                'summary' => 'Collect supplier prices and select the best option per item.',
                'checklist' => [
                    'Enter or upload supplier prices for the requested items.',
                    'Review and compare quotes; select the best option(s) per item.',
                    'Create a Quotation Evaluation (QE) when required; it must be approved before Buyer Quotes.',
                    'If supplier is obtained, no need to create a Quotation Evaluation (QE).',
                    'Use Send to buyer to send the selected quotes to the buyer.',
                ],
                'next' => 'Once sent, continue to Buyer Quotes.',
            ],
            RequestStage::PREPARING_BUYER_QUOTE => [
                'summary' => 'Build the buyer quote, set pricing, and get it accepted.',
                'checklist' => [
                    'Create the buyer quote from the selected supplier-quote items.',
                    'Set selling prices, margins, and tax; verify details and terms.',
                    'Create a Profit & Loss (P&L) document — it must be approved before invoices and later stages.',
                    'Use Send to send the quote to the buyer.',
                    'When the buyer sends a PO, use Upload PO so the status becomes Accepted.',
                ],
                'next' => 'Once at least one quote is accepted, continue to Supplier Orders.',
            ],
            RequestStage::PREPARING_SUPPLIER_ORDER => [
                'summary' => 'Create purchase orders to suppliers, get them approved, and send them.',
                'checklist' => [
                    'Create purchase orders from accepted buyer quote(s) — there may be one PO per supplier.',
                    'Verify quantities, prices, and terms, then Confirm each order to request approval.',
                    'Get each order approved: 2 approvals required (Dept Head of Sales, Deputy Director, or Director).',
                    'Use Send PO to Supplier to email each approved PO to its supplier.',
                ],
                'next' => 'Goods Receive unlocks once every order is approved and sent.',
            ],
            RequestStage::GOODS_RECEIVE => [
                'summary' => 'Receive the delivered goods and approve the paperwork.',
                'checklist' => [
                    'Available only after all supplier orders are approved and sent.',
                    'Upload goods receive documents (delivery notes, packing lists, etc.).',
                    'All documents must be approved via Approval > Goods Receive.',
                ],
                'next' => 'Approved documents unlock Fulfillment (goods shipments).',
            ],
            RequestStage::AWAITING_BUYER_CONFIRMATION => [
                'summary' => 'Invoice the buyer from the accepted quote(s).',
                'checklist' => [
                    'Available once P&L is approved and at least one buyer quote is Accepted.',
                    'Buyer orders are created from accepted buyer quote(s) and act as the invoice to the buyer.',
                    'Open the buyer order and review items, pricing, and terms.',
                    'Use Confirm to confirm the order (this can create or link to supplier orders).',
                    'Use Send to send the order to the buyer when ready.',
                ],
                'next' => 'Once sent, invoicing is complete; shipments proceed once Goods Receive is approved.',
            ],
            RequestStage::AWAITING_SHIPMENT => [
                'summary' => 'Record fulfillment for each channel of this request.',
                'checklist' => [
                    'Goods — create inbound shipments and mark them delivered.',
                    'Services — file acceptance reports for completed service items.',
                    'For a mixed request, both channels appear here.',
                ],
                'next' => 'Continue to Completion Report after delivery.',
            ],
            RequestStage::SHIPPED, RequestStage::DELIVERED => [
                'summary' => 'Document project completion after delivery.',
                'checklist' => [
                    'Available after shipments are delivered.',
                    'Upload completion report documentation.',
                    'Documents are stored securely and can be downloaded or viewed at any time.',
                ],
                'next' => 'The request is complete once the report is filed.',
            ],
            default => [
                'summary' => '',
                'checklist' => [],
                'next' => '',
            ],
        };
    }
}
