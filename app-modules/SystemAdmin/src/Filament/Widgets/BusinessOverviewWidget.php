<?php

declare(strict_types=1);

namespace Relaticle\SystemAdmin\Filament\Widgets;

use App\Enums\CreationSource;
use App\Models\Company;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class BusinessOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $businessData = $this->getBusinessData();

        return [
            Stat::make('Total Companies', number_format($businessData['total_companies']))
                ->description($this->getGrowthDescription($businessData['companies_growth'], 'companies'))
                ->descriptionIcon($businessData['companies_growth'] >= 0 ? 'heroicon-o-building-office-2' : 'heroicon-o-building-office')
                ->color($this->getGrowthColor($businessData['companies_growth']))
                ->extraAttributes([
                    'class' => 'relative overflow-hidden',
                ]),
        ];
    }

    /**
     * @return array{total_companies: int, companies_growth: float}
     */
    private function getBusinessData(): array
    {
        $totalCompanies = Company::where('creation_source', '!=', CreationSource::SYSTEM)->count();

        return [
            'total_companies' => $totalCompanies,
            'companies_growth' => $this->calculateCompaniesGrowth(),
        ];
    }

    private function calculateCompaniesGrowth(): float
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $companiesThisMonth = Company::where('created_at', '>=', $currentMonth)
            ->where('creation_source', '!=', CreationSource::SYSTEM)
            ->count();
        $companiesLastMonth = Company::whereBetween('created_at', [$lastMonth, $currentMonth])
            ->where('creation_source', '!=', CreationSource::SYSTEM)
            ->count();

        return $this->calculateGrowthRate($companiesThisMonth, $companiesLastMonth);
    }

    private function calculateGrowthRate(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100);
    }

    private function getGrowthDescription(float $growth, string $type): string
    {
        return match (true) {
            $growth > 50 => "Exceptional {$type} growth this month",
            $growth > 20 => "Strong {$type} growth this month",
            $growth > 0 => "Positive {$type} growth this month",
            default => "Declining {$type} this month"
        };
    }

    private function getGrowthColor(float $growth): string
    {
        return match (true) {
            $growth > 20 => 'success',
            $growth > 0 => 'info',
            $growth === 0.0 => 'warning',
            default => 'danger'
        };
    }
}
