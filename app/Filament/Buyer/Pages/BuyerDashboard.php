<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Pages;

use App\Filament\Buyer\Widgets\PortalActionItemsWidget;
use App\Filament\Buyer\Widgets\PortalActiveShipmentsWidget;
use App\Filament\Buyer\Widgets\PortalRecentRequestsWidget;
use App\Filament\Buyer\Widgets\PortalRequestsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

final class BuyerDashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Home';

    protected static ?int $navigationSort = -2;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            PortalActionItemsWidget::class,
            PortalRequestsOverviewWidget::class,
            PortalActiveShipmentsWidget::class,
            PortalRecentRequestsWidget::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }
}
