<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RequestStage;
use App\Models\Request;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ActiveRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $teamId = Filament::getTenant()?->getKey();

        if ($teamId === null) {
            return [];
        }

        $quotationStages = [
            RequestStage::DRAFT,
            RequestStage::QUOTING_SUPPLIER,
            RequestStage::QUOTING_BUYER,
            RequestStage::QUOTE_SENT,
            RequestStage::QUOTE_ACCEPTED,
        ];

        $orderingStages = [
            RequestStage::ORDERED,
            RequestStage::IN_PROGRESS,
        ];

        $fulfillmentStages = [
            RequestStage::SHIPPED,
            RequestStage::DELIVERED,
            RequestStage::INVOICED,
            RequestStage::PAID,
        ];

        $baseQuery = Request::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNotIn('stage', [RequestStage::COMPLETED, RequestStage::CANCELLED]);

        $totalActive = (clone $baseQuery)->count();

        $quotationCount = (clone $baseQuery)
            ->whereIn('stage', $quotationStages)
            ->count();

        $orderingCount = (clone $baseQuery)
            ->whereIn('stage', $orderingStages)
            ->count();

        $fulfillmentCount = (clone $baseQuery)
            ->whereIn('stage', $fulfillmentStages)
            ->count();

        return [
            Stat::make('Active Requests', (string) $totalActive)
                ->description('Total active requests')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary')
                ->chart($this->getRecentRequestsChart($teamId)),

            Stat::make('Quotation Stage', (string) $quotationCount)
                ->description('Draft to Quote Accepted')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Ordering Stage', (string) $orderingCount)
                ->description('Ordered & In Progress')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning'),

            Stat::make('Fulfillment Stage', (string) $fulfillmentCount)
                ->description('Shipped to Paid')
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),
        ];
    }

    /**
     * Get chart data for recent requests trend.
     *
     * @return array<int>
     */
    private function getRecentRequestsChart(int $teamId): array
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Request::query()
                ->where('team_id', $teamId)
                ->whereDate('created_at', $date)
                ->count();
            $data[] = $count;
        }

        return $data;
    }
}
