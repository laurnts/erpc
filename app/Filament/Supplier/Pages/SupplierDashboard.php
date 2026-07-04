<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Pages;

use App\Filament\Supplier\Widgets\SupplierOpenRfqsWidget;
use App\Filament\Supplier\Widgets\SupplierRfqOutcomesWidget;
use App\Filament\Supplier\Widgets\SupplierStalePricesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

final class SupplierDashboard extends BaseDashboard
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
            SupplierStalePricesWidget::class,
            SupplierOpenRfqsWidget::class,
            SupplierRfqOutcomesWidget::class,
        ];
    }

    /**
     * @return array<string, ?int>
     */
    public function getColumns(): array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }
}
