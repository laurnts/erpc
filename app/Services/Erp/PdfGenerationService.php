<?php

declare(strict_types=1);

namespace App\Services\Erp;

use App\Data\TeamErpSettings;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\SupplierOrder;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;

final readonly class PdfGenerationService
{
    /**
     * Generate PDF for a buyer quote.
     */
    public function generateBuyerQuotePdf(BuyerQuote $quote): string
    {
        $quote->load(['buyer', 'currency', 'items', 'team']);

        $pdf = Pdf::loadView('pdf.buyer-quote', [
            'quote' => $quote,
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
