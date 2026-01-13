<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\BuyerInvoice;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class MonthlyRevenueWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $teamId = Filament::getTenant()?->getKey();

        if ($teamId === null) {
            return [];
        }

        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        // Current month revenue (paid invoices)
        $currentMonthRevenue = BuyerInvoice::query()
            ->where('team_id', $teamId)
            ->where('status', InvoiceStatus::PAID)
            ->whereBetween('updated_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('total');

        // Previous month revenue
        $previousMonthRevenue = BuyerInvoice::query()
            ->where('team_id', $teamId)
            ->where('status', InvoiceStatus::PAID)
            ->whereBetween('updated_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('total');

        // Calculate trend
        $trend = $this->calculateTrend((float) $currentMonthRevenue, (float) $previousMonthRevenue);

        // Get invoice counts
        $currentMonthInvoices = BuyerInvoice::query()
            ->where('team_id', $teamId)
            ->where('status', InvoiceStatus::PAID)
            ->whereBetween('updated_at', [$currentMonthStart, $currentMonthEnd])
            ->count();

        $previousMonthInvoices = BuyerInvoice::query()
            ->where('team_id', $teamId)
            ->where('status', InvoiceStatus::PAID)
            ->whereBetween('updated_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        // Pending revenue (outstanding invoices)
        $pendingRevenue = BuyerInvoice::query()
            ->where('team_id', $teamId)
            ->whereIn('status', [InvoiceStatus::SENT, InvoiceStatus::PARTIAL, InvoiceStatus::OVERDUE])
            ->selectRaw('SUM(CAST(total AS DECIMAL(15,4)) - CAST(amount_paid AS DECIMAL(15,4))) as outstanding')
            ->value('outstanding') ?? 0;

        return [
            Stat::make('Current Month Revenue', $this->formatCurrency((float) $currentMonthRevenue))
                ->description($trend['description'])
                ->descriptionIcon($trend['icon'])
                ->color($trend['color'])
                ->chart($this->getRevenueChart($teamId)),

            Stat::make('Invoices Paid', (string) $currentMonthInvoices)
                ->description($this->getInvoiceTrendDescription($currentMonthInvoices, $previousMonthInvoices))
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success'),

            Stat::make('Pending Revenue', $this->formatCurrency((float) $pendingRevenue))
                ->description('Outstanding invoices')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingRevenue > 0 ? 'warning' : 'success'),

            Stat::make('Previous Month', $this->formatCurrency((float) $previousMonthRevenue))
                ->description(now()->subMonth()->format('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('gray'),
        ];
    }

    /**
     * Calculate the trend between current and previous values.
     *
     * @return array{description: string, icon: string, color: string}
     */
    private function calculateTrend(float $current, float $previous): array
    {
        if ($previous === 0.0) {
            if ($current > 0) {
                return [
                    'description' => 'New revenue this month',
                    'icon' => 'heroicon-m-arrow-trending-up',
                    'color' => 'success',
                ];
            }

            return [
                'description' => 'No revenue yet',
                'icon' => 'heroicon-m-minus',
                'color' => 'gray',
            ];
        }

        $percentChange = (($current - $previous) / $previous) * 100;
        $formattedPercent = number_format(abs($percentChange), 1);

        if ($percentChange > 0) {
            return [
                'description' => $formattedPercent.'% increase',
                'icon' => 'heroicon-m-arrow-trending-up',
                'color' => 'success',
            ];
        }

        if ($percentChange < 0) {
            return [
                'description' => $formattedPercent.'% decrease',
                'icon' => 'heroicon-m-arrow-trending-down',
                'color' => 'danger',
            ];
        }

        return [
            'description' => 'No change',
            'icon' => 'heroicon-m-minus',
            'color' => 'gray',
        ];
    }

    /**
     * Get invoice trend description.
     */
    private function getInvoiceTrendDescription(int $current, int $previous): string
    {
        $diff = $current - $previous;

        if ($diff === 0) {
            return 'Same as last month';
        }

        $direction = $diff > 0 ? 'more' : 'fewer';

        return abs($diff).' '.$direction.' than last month';
    }

    /**
     * Format a number as currency.
     */
    private function formatCurrency(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    /**
     * Get chart data for daily revenue in the current month.
     *
     * @return array<int>
     */
    private function getRevenueChart(int $teamId): array
    {
        $data = [];
        $startOfMonth = now()->startOfMonth();

        for ($i = 0; $i < min(now()->day, 14); $i++) {
            $date = $startOfMonth->copy()->addDays($i);
            $revenue = BuyerInvoice::query()
                ->where('team_id', $teamId)
                ->where('status', InvoiceStatus::PAID)
                ->whereDate('updated_at', $date)
                ->sum('total');
            $data[] = (int) $revenue;
        }

        return $data;
    }
}
