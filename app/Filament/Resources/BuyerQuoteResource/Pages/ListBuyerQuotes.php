<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerQuoteResource\Pages;

use App\Filament\Exports\BuyerQuoteExporter;
use App\Filament\Resources\BuyerQuoteResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListBuyerQuotes extends ListRecords
{
    protected static string $resource = BuyerQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()->exporter(BuyerQuoteExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Export')
                ->size(Size::Small),
        ];
    }
}
