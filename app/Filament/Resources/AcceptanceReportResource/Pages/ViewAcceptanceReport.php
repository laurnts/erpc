<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcceptanceReportResource\Pages;

use App\Filament\Resources\AcceptanceReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAcceptanceReport extends ViewRecord
{
    /** @var class-string<AcceptanceReportResource> */
    protected static string $resource = AcceptanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
