<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfitAndLossResource\Pages;

use App\Filament\Exports\ProfitAndLossExporter;
use App\Filament\Resources\ProfitAndLossResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListProfitAndLosses extends ListRecords
{
    /** @var class-string<ProfitAndLossResource> */
    protected static string $resource = ProfitAndLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()->exporter(ProfitAndLossExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Export')
                ->size(Size::Small),
        ];
    }
}
