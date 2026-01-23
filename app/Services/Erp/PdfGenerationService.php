<?php

declare(strict_types=1);

namespace App\Services\Erp;

use App\Data\TeamErpSettings;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\SupplierOrder;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;

final readonly class PdfGenerationService
{
    /**
     * Generate PDF for a buyer quote.
     */
    public function generateBuyerQuotePdf(BuyerQuote $quote): string
    {
        $quote->load(['buyer', 'currency', 'items', 'paymentTerms', 'team']);

        // Process items: filter hidden items and distribute their prices
        $visibleItems = $quote->items->filter(fn ($item): bool => ! $item->hide_from_pdf);
        $hiddenItems = $quote->items->filter(fn ($item) => $item->hide_from_pdf);

        // Calculate total price of hidden items (line_total)
        $hiddenTotal = $hiddenItems->sum(fn ($item): float => (float) $item->line_total);

        // Distribute hidden item prices evenly among visible items
        $visibleCount = $visibleItems->count();
        $distributionPerItem = $visibleCount > 0 ? $hiddenTotal / $visibleCount : 0;

        // Create processed items with distributed prices
        $processedItems = $visibleItems->map(function ($item) use ($distributionPerItem): object {
            $quantity = (float) $item->quantity;

            // Add distribution amount directly to unit price (not divided by quantity)
            $originalUnitPrice = (float) $item->unit_price_exc_tax;
            $newUnitPriceExcTax = $originalUnitPrice + $distributionPerItem;

            // Recalculate line subtotal (no tax per item)
            $newLineSubtotal = $newUnitPriceExcTax * $quantity;

            // Set tax to 0 for items (tax will be calculated from subtotal)
            $newLineTax = 0;
            $newLineTotal = $newLineSubtotal;

            // Create a new object with adjusted values
            $processedItem = clone $item;
            $processedItem->unit_price_exc_tax = (string) round($newUnitPriceExcTax, 4);
            $processedItem->line_subtotal = (string) round($newLineSubtotal, 4);
            $processedItem->line_tax = (string) round($newLineTax, 4);
            $processedItem->line_total = (string) round($newLineTotal, 4);
            $processedItem->tax_amount = (string) round(0, 4);

            return $processedItem;
        });

        // Calculate subtotal from processed items
        $processedSubtotal = $processedItems->sum(fn ($item): float => (float) $item->line_subtotal);

        // Calculate tax rate from visible items
        // Use tax rate from first visible item that has tax
        $taxRate = 0;
        $itemsWithTax = $visibleItems->filter(fn ($item): bool => (float) $item->tax_rate > 0);

        if ($itemsWithTax->isNotEmpty()) {
            // Use tax rate from first item with tax (all items should have same tax rate)
            $firstItemWithTax = $itemsWithTax->first();
            $taxRate = (float) $firstItemWithTax->tax_rate;
        }

        // Calculate tax from subtotal
        $processedTaxTotal = $processedSubtotal * ($taxRate / 100);
        $processedTotal = $processedSubtotal + $processedTaxTotal;

        $pdf = Pdf::loadView('pdf.buyer-quote', [
            'quote' => $quote,
            'items' => $processedItems,
            'processedSubtotal' => $processedSubtotal,
            'processedTaxTotal' => $processedTaxTotal,
            'processedTotal' => $processedTotal,
            'company' => $this->getCompanyDetails($quote->team),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate PDF for a buyer order confirmation.
     */
    public function generateBuyerOrderPdf(BuyerOrder $order): string
    {
        $order->load(['buyer', 'items', 'request', 'team']);

        $pdf = Pdf::loadView('pdf.buyer-order', [
            'order' => $order,
            'company' => $this->getCompanyDetails($order->team),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate PDF for a buyer invoice.
     */
    public function generateBuyerInvoicePdf(BuyerInvoice $invoice): string
    {
        $invoice->load(['buyerOrder.buyer', 'currency', 'items', 'payments', 'request', 'team']);

        $pdf = Pdf::loadView('pdf.buyer-invoice', [
            'invoice' => $invoice,
            'company' => $this->getCompanyDetails($invoice->team),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate PDF for a supplier order (Purchase Order).
     */
    public function generateSupplierOrderPdf(SupplierOrder $order): string
    {
        $order->load(['supplier', 'currency', 'items', 'team']);

        $pdf = Pdf::loadView('pdf.supplier-order', [
            'order' => $order,
            'company' => $this->getCompanyDetails($order->team),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Get the filename for a buyer quote PDF.
     */
    public function getBuyerQuoteFilename(BuyerQuote $quote): string
    {
        return sprintf('Quote_%s_v%d.pdf', $quote->quote_number, $quote->version);
    }

    /**
     * Get the filename for a buyer order PDF.
     */
    public function getBuyerOrderFilename(BuyerOrder $order): string
    {
        return sprintf('Order_%s.pdf', $order->order_number);
    }

    /**
     * Get the filename for a buyer invoice PDF.
     */
    public function getBuyerInvoiceFilename(BuyerInvoice $invoice): string
    {
        return sprintf('Invoice_%s.pdf', $invoice->invoice_number);
    }

    /**
     * Get the filename for a supplier order PDF.
     */
    public function getSupplierOrderFilename(SupplierOrder $order): string
    {
        return sprintf('PO_%s.pdf', $order->po_number);
    }

    /**
     * Generate PDF for a quotation evaluation.
     */
    public function generateQuotationEvaluationPdf(QuotationEvaluation $qe): DomPDF
    {
        $qe->load(['request', 'preparedBy', 'team']);

        $pdf = Pdf::loadView('pdf.quotation-evaluation', [
            'qe' => $qe,
            'company' => $this->getCompanyDetails($qe->team),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Get the filename for a quotation evaluation PDF.
     */
    public function getQuotationEvaluationFilename(QuotationEvaluation $qe): string
    {
        $sanitizedNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', $qe->qe_number);

        return sprintf('QE-%s.pdf', $sanitizedNumber);
    }

    /**
     * Generate PDF for a profit and loss document.
     */
    public function generateProfitAndLossPdf(ProfitAndLoss $pnl): DomPDF
    {
        $pnl->load(['request', 'buyerQuote', 'preparedBy', 'team']);

        $pdf = Pdf::loadView('pdf.profit-and-loss', [
            'pnl' => $pnl,
            'company' => $this->getCompanyDetails($pnl->team),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Get the filename for a profit and loss PDF.
     */
    public function getProfitAndLossFilename(ProfitAndLoss $pnl): string
    {
        $sanitizedNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', $pnl->pnl_number);

        return sprintf('PNL-%s.pdf', $sanitizedNumber);
    }

    /**
     * Get company details from team's ERP settings.
     *
     * @return array{name: string, address: string, phone: string, email: string}
     */
    private function getCompanyDetails(?Team $team): array
    {
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;

        return [
            'name' => $settings->company_name,
            'address' => $settings->company_address,
            'phone' => $settings->company_phone,
            'email' => $settings->company_email,
        ];
    }
}
