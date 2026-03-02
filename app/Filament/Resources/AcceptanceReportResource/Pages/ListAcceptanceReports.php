<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcceptanceReportResource\Pages;

use App\Filament\Resources\AcceptanceReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAcceptanceReports extends ListRecords
{
    /** @var class-string<AcceptanceReportResource> */
    protected static string $resource = AcceptanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
