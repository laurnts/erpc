<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BuyerPayment;
use App\Models\Request as ErpRequest;
use App\Models\SupplierPayment;
use App\Services\Portal\BuyerPortalContext;
use App\Services\Portal\SupplierPortalContext;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineParty;
use App\Support\Media\DocumentResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Panel-scoped document download for portal parties (timeline file links).
 *
 * Fail-closed mirror of the portal timeline: the media's owning model must
 * resolve to a request in the portal user's team, and the media must pass
 * the party's subject allow-list and MediaRule (allowsMedia); anything else
 * is a 404. Always serves an attachment so every file type behaves the same.
 */
final readonly class PortalDocumentDownloadController
{
    public function __invoke(Request $request, Media $media): BinaryFileResponse
    {
        $portal = (string) $request->route('portal');
        $context = $portal === 'buyer'
            ? app(BuyerPortalContext::class)
            : app(SupplierPortalContext::class);

        $erpRequest = $this->owningRequest($media);

        if (! $erpRequest instanceof ErpRequest || (int) $erpRequest->team_id !== $context->teamId()) {
            abort(404);
        }

        $party = $portal === 'buyer'
            ? TimelineParty::buyer($context->companyId())
            : TimelineParty::supplier($context->companyId());

        abort_unless(app(PortalTimelineSource::class)->allowsMedia($erpRequest, $party, $media), 404);

        $filePath = $media->getPath();

        abort_unless(file_exists($filePath), 404);

        return DocumentResponse::make($media, $filePath, forceDownload: true);
    }

    /**
     * The request that owns the media's model. Payments hang off their
     * invoice; every other portal-visible subject carries request_id.
     */
    private function owningRequest(Media $media): ?ErpRequest
    {
        $model = $media->model;

        if ($model instanceof ErpRequest) {
            return $model;
        }

        if ($model instanceof BuyerPayment) {
            return $model->buyerInvoice->request;
        }

        if ($model instanceof SupplierPayment) {
            return $model->supplierInvoice->request;
        }

        $requestId = $model?->getAttribute('request_id');

        if ($requestId === null) {
            return null;
        }

        return ErpRequest::query()->find((int) $requestId);
    }
}
