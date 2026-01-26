<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Shipment;
use App\Models\SupplierOrder;
use App\Services\Erp\PdfGenerationService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadPdfAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'downloadPdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF')
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->action(fn (Model $record): StreamedResponse => $this->downloadPdf($record));
    }

    private function downloadPdf(Model $record): StreamedResponse
    {
        $pdfService = app(PdfGenerationService::class);

        $content = match (true) {
            $record instanceof BuyerQuote => $pdfService->generateBuyerQuotePdf($record),
            $record instanceof BuyerOrder => $pdfService->generateBuyerOrderPdf($record),
            $record instanceof BuyerInvoice => $pdfService->generateBuyerInvoicePdf($record),
            $record instanceof SupplierOrder => $pdfService->generateSupplierOrderPdf($record),
            $record instanceof Shipment => $pdfService->generateShipmentDeliveryOrderPdf($record),
            default => throw new \InvalidArgumentException('Unsupported model type for PDF generation: '.$record::class),
        };

        $filename = match (true) {
            $record instanceof BuyerQuote => $pdfService->getBuyerQuoteFilename($record),
            $record instanceof BuyerOrder => $pdfService->getBuyerOrderFilename($record),
            $record instanceof BuyerInvoice => $pdfService->getBuyerInvoiceFilename($record),
            $record instanceof SupplierOrder => $pdfService->getSupplierOrderFilename($record),
            $record instanceof Shipment => $pdfService->getShipmentDeliveryOrderFilename($record),
            default => 'document.pdf',
        };

        return response()->streamDownload(
            callback: static function () use ($content): void {
                echo $content;
            },
            name: $filename,
            headers: [
                'Content-Type' => 'application/pdf',
            ],
        );
    }
}
