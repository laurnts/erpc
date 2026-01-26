<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\Erp\PdfGenerationService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ShipmentPdfController
{
    public function __invoke(Shipment $shipment): StreamedResponse|Response
    {
        // Ensure user is authenticated and has access to this shipment
        if (!auth()->check()) {
            abort(403);
        }

        // Verify user has access to the shipment's team
        if ($shipment->team_id !== auth()->user()->currentTeam?->id) {
            abort(403);
        }

        // Only allow inbound shipments
        if ($shipment->type !== \App\Enums\ShipmentType::INBOUND) {
            abort(404);
        }

        $pdfService = app(PdfGenerationService::class);
        $content = $pdfService->generateShipmentDeliveryOrderPdf($shipment);
        $filename = $pdfService->getShipmentDeliveryOrderFilename($shipment);

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
