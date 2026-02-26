<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOrderResource\Pages;

use App\Filament\Exports\SupplierOrderExporter;
use App\Filament\Resources\SupplierOrderResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListSupplierOrders extends ListRecords
{
    protected static string $resource = SupplierOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()->exporter(SupplierOrderExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Export')
                ->size(Size::Small),
        ];
    }
}
