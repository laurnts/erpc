<?php

declare(strict_types=1);

namespace App\Filament\Customer\Pages;

use App\Filament\Customer\Widgets\PortalActiveShipmentsWidget;
use App\Filament\Customer\Widgets\PortalRecentRequestsWidget;
use App\Filament\Customer\Widgets\PortalRequestsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

final class CustomerDashboard extends BaseDashboard
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
            PortalRequestsOverviewWidget::class,
            PortalActiveShipmentsWidget::class,
            PortalRecentRequestsWidget::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }
}
