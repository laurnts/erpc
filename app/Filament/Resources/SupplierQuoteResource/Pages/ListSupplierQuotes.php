<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierQuoteResource\Pages;

use App\Filament\Exports\SupplierQuoteExporter;
use App\Filament\Resources\SupplierQuoteResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListSupplierQuotes extends ListRecords
{
    protected static string $resource = SupplierQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()->exporter(SupplierQuoteExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Export')
                ->size(Size::Small),
        ];
    }
}
