<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Filament\Exports\BuyerExporter;
use App\Filament\Imports\BuyerImporter;
use App\Filament\Resources\BuyerResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListBuyers extends ListRecords
{
    protected static string $resource = BuyerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ImportAction::make()->importer(BuyerImporter::class),
                ExportAction::make()->exporter(BuyerExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Import / Export')
                ->size(Size::Small),
            CreateAction::make()
                ->slideOver()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['is_buyer'] = true;

                    return $data;
                }),
        ];
    }
}
