<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RequestStage;
use App\Models\Request;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

final class PipelineByStageWidget extends ChartWidget
{
    protected ?string $heading = 'Pipeline by Stage';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $teamId = Filament::getTenant()?->getKey();

        if ($teamId === null) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get count and value per stage
        $stageData = Request::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNotIn('stage', [RequestStage::COMPLETED, RequestStage::CANCELLED])
            ->select('stage', DB::raw('COUNT(*) as count'))
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        // Get buyer totals per stage
        $stageValues = Request::query()
            ->where('requests.team_id', $teamId)
            ->where('requests.is_active', true)
            ->whereNotIn('requests.stage', [RequestStage::COMPLETED, RequestStage::CANCELLED])
            ->join('buyer_orders', 'requests.id', '=', 'buyer_orders.request_id')
            ->select('requests.stage', DB::raw('SUM(CAST(buyer_orders.total AS DECIMAL(15,2))) as total_value'))
            ->groupBy('requests.stage')
            ->withoutGlobalScopes()
            ->get()
            ->keyBy('stage');

        $activeStages = [
            RequestStage::DRAFT,
            RequestStage::AWAITING_SUPPLIER_RESPONSE,
            RequestStage::PREPARING_BUYER_QUOTE,
            RequestStage::AWAITING_BUYER_CONFIRMATION,
            RequestStage::PREPARING_SUPPLIER_ORDER,
            RequestStage::AWAITING_SHIPMENT,
            RequestStage::SHIPPED,
            RequestStage::DELIVERED,
            RequestStage::INVOICED,
            RequestStage::PAID,
        ];

        $labels = [];
        $counts = [];
        $values = [];
        $backgroundColors = [];

        foreach ($activeStages as $stage) {
            $stageKey = $stage->value;
            $labels[] = $stage->getLabel();
            $counts[] = $stageData[$stageKey]->count ?? 0;
            $values[] = (float) ($stageValues[$stageKey]->total_value ?? 0);
            $backgroundColors[] = $this->getStageColor($stage);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Request Count',
                    'data' => $counts,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Value ($)',
                    'data' => $values,
                    'backgroundColor' => array_map(fn (string $color): string => $this->adjustOpacity($color, 0.6), $backgroundColors),
                    'borderRadius' => 4,
                    'yAxisID' => 'y1',
                    'hidden' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Request Count',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Value ($)',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
        ];
    }

    /**
     * Get the color for a stage.
     */
    private function getStageColor(RequestStage $stage): string
    {
        return match ($stage->getColor()) {
            'gray' => 'rgba(107, 114, 128, 0.8)',
            'info' => 'rgba(59, 130, 246, 0.8)',
            'warning' => 'rgba(245, 158, 11, 0.8)',
            'success' => 'rgba(34, 197, 94, 0.8)',
            'primary' => 'rgba(139, 92, 246, 0.8)',
            'danger' => 'rgba(239, 68, 68, 0.8)',
            default => 'rgba(107, 114, 128, 0.8)',
        };
    }

    /**
     * Adjust the opacity of an rgba color.
     */
    private function adjustOpacity(string $color, float $opacity): string
    {
        return preg_replace('/[\d.]+\)$/', $opacity.')', $color) ?? $color;
    }
}
