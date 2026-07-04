<?php

declare(strict_types=1);

namespace Relaticle\SystemAdmin\Filament\Widgets;

use App\Enums\CreationSource;
use App\Models\Company;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Relaticle\SystemAdmin\Filament\Widgets\Concerns\HasCustomFieldQueries;

final class BusinessOverviewWidget extends BaseWidget
{
    use HasCustomFieldQueries;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $businessData = $this->getBusinessData();

        return [
            Stat::make('Task Completion', $businessData['completion_rate'].'%')
                ->description($this->getCompletionDescription($businessData['completion_rate']))
                ->descriptionIcon($this->getCompletionIcon($businessData['completion_rate']))
                ->color($this->getCompletionColor($businessData['completion_rate']))
                ->extraAttributes([
                    'class' => 'relative overflow-hidden',
                ]),

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
     * @return array{completion_rate: float, total_companies: int, companies_growth: float}
     */
    private function getBusinessData(): array
    {
        $totalTasks = Task::where('creation_source', '!=', CreationSource::SYSTEM)->count();
        $completedTasks = $this->countCompletedEntities('tasks', 'task', 'status');
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        $totalCompanies = Company::where('creation_source', '!=', CreationSource::SYSTEM)->count();

        return [
            'completion_rate' => $completionRate,
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

    private function getCompletionDescription(float $rate): string
    {
        return match (true) {
            $rate >= 90 => 'Exceptional team productivity',
            $rate >= 70 => 'Strong team performance',
            $rate >= 50 => 'Average team productivity',
            $rate > 0 => 'Below average performance',
            default => 'No completed tasks tracked'
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

    private function getCompletionColor(float $rate): string
    {
        return match (true) {
            $rate >= 80 => 'success',
            $rate >= 60 => 'info',
            $rate >= 40 => 'warning',
            default => 'danger'
        };
    }

    private function getCompletionIcon(float $rate): string
    {
        return match (true) {
            $rate >= 80 => 'heroicon-o-check-badge',
            $rate >= 60 => 'heroicon-o-check-circle',
            $rate >= 40 => 'heroicon-o-clock',
            default => 'heroicon-o-exclamation-triangle'
        };
    }
}
