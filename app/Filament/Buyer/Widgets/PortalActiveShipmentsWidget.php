<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Widgets;

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\Shipment;
use App\Services\Portal\BuyerPortalContext;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PortalActiveShipmentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'buyer';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $companyId = app(BuyerPortalContext::class)->companyId();

        $baseQuery = Shipment::query()
            ->where('type', ShipmentType::OUTBOUND)
            ->forBuyerCompany($companyId);

        $inTransit = (clone $baseQuery)
            ->where('status', ShipmentStatus::IN_TRANSIT)
            ->count();

        $pending = (clone $baseQuery)
            ->where('status', ShipmentStatus::PENDING)
            ->count();

        $delivered = (clone $baseQuery)
            ->where('status', ShipmentStatus::DELIVERED)
            ->count();

        return [
            Stat::make('In Transit', (string) $inTransit)
                ->description('Shipments in transit')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Awaiting Shipment', (string) $pending)
                ->description('Not yet shipped')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Delivered', (string) $delivered)
                ->description('Shipments completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
