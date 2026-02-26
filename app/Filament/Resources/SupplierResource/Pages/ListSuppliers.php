<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Exports\SupplierExporter;
use App\Filament\Imports\SupplierImporter;
use App\Filament\Resources\SupplierResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ImportAction::make()->importer(SupplierImporter::class),
                ExportAction::make()->exporter(SupplierExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Import / Export')
                ->size(Size::Small),
            CreateAction::make()
                ->slideOver()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['is_supplier'] = true;

                    return $data;
                }),
        ];
    }
}
