<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerOrderResource\Pages;

use App\Filament\Exports\BuyerOrderExporter;
use App\Filament\Resources\BuyerOrderResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListBuyerOrders extends ListRecords
{
    protected static string $resource = BuyerOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()->exporter(BuyerOrderExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Export')
                ->size(Size::Small),
        ];
    }
}
