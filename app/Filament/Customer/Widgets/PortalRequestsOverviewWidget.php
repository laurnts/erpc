<?php

declare(strict_types=1);

namespace App\Filament\Customer\Widgets;

use App\Enums\RequestStage;
use App\Models\Request;
use App\Services\Portal\CustomerPortalContext;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PortalRequestsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'customer';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $companyId = app(CustomerPortalContext::class)->companyId();

        $baseQuery = Request::query()
            ->forBuyer($companyId)
            ->whereNotNull('submitted_at');

        $activeCount = (clone $baseQuery)
            ->whereNotIn('stage', [RequestStage::COMPLETED, RequestStage::CANCELLED])
            ->count();

        $awaitingConfirmation = (clone $baseQuery)
            ->where('stage', RequestStage::AWAITING_BUYER_CONFIRMATION)
            ->count();

        $inFulfillment = (clone $baseQuery)
            ->whereIn('stage', [
                RequestStage::PREPARING_SUPPLIER_ORDER,
                RequestStage::GOODS_RECEIVE,
                RequestStage::AWAITING_SHIPMENT,
                RequestStage::SHIPPED,
                RequestStage::DELIVERED,
            ])
            ->count();

        $completedCount = (clone $baseQuery)
            ->whereIn('stage', [RequestStage::COMPLETED, RequestStage::PAID, RequestStage::INVOICED])
            ->count();

        return [
            Stat::make('Active Requests', (string) $activeCount)
                ->description('Not yet completed')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Awaiting Confirmation', (string) $awaitingConfirmation)
                ->description('Quotes need review')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('warning'),

            Stat::make('In Fulfillment', (string) $inFulfillment)
                ->description('Processing until delivered')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Completed', (string) $completedCount)
                ->description('Requests completed')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
