<?php

declare(strict_types=1);

namespace App\Filament\Customer\Widgets;

use App\Enums\BuyerQuoteStatus;
use App\Enums\RequestStage;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Models\BuyerQuote;
use App\Models\Request;
use App\Models\Shipment;
use App\Services\Portal\CustomerPortalContext;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

final class PortalActionItemsWidget extends Widget
{
    protected string $view = 'filament.customer.widgets.portal-action-items';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'customer';
    }

    /**
     * @return array<int, array{type: string, label: string, reference: string, description: string, url: string, icon: string, color: string}>
     */
    public function getActionItems(): array
    {
        $companyId = app(CustomerPortalContext::class)->companyId();
        $items = [];

        BuyerQuote::query()
            ->where('buyer_id', $companyId)
            ->where('status', BuyerQuoteStatus::SENT)
            ->with('request')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(function (BuyerQuote $quote) use (&$items): void {
                if ($quote->request === null) {
                    return;
                }

                $items[] = [
                    'type' => 'quote',
                    'label' => 'Quote awaiting confirmation',
                    'reference' => $quote->quote_number,
                    'description' => $quote->request->title,
                    'url' => CustomerRequestResource::getUrl('view', ['record' => $quote->request_id]),
                    'icon' => 'Q',
                    'color' => 'warning',
                ];
            });

        Request::query()
            ->where('buyer_id', $companyId)
            ->where('stage', RequestStage::DRAFT)
            ->whereNotNull('submitted_at')
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->each(function (Request $request) use (&$items): void {
                if (! $request->isEditableByCustomer()) {
                    return;
                }

                $items[] = [
                    'type' => 'draft',
                    'label' => 'Draft request can be edited',
                    'reference' => $request->request_number,
                    'description' => $request->title,
                    'url' => CustomerRequestResource::getUrl('edit', ['record' => $request]),
                    'icon' => 'D',
                    'color' => 'primary',
                ];
            });

        Shipment::query()
            ->where('type', ShipmentType::OUTBOUND)
            ->where('status', ShipmentStatus::IN_TRANSIT)
            ->whereHas('request', fn ($query) => $query->where('buyer_id', $companyId))
            ->with('request')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(function (Shipment $shipment) use (&$items): void {
                if ($shipment->request === null) {
                    return;
                }

                $items[] = [
                    'type' => 'shipment',
                    'label' => 'Shipment in transit',
                    'reference' => $shipment->shipment_number,
                    'description' => $shipment->request->title,
                    'url' => CustomerRequestResource::getUrl('view', ['record' => $shipment->request_id]),
                    'icon' => 'S',
                    'color' => 'info',
                ];
            });

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'items' => $this->getActionItems(),
        ];
    }
}
