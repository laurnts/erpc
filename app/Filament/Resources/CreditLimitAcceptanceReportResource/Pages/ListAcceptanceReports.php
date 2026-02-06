<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditLimitAcceptanceReportResource\Pages;

use App\Filament\Resources\CreditLimitAcceptanceReportResource;
use Filament\Resources\Pages\ListRecords;

final class ListAcceptanceReports extends ListRecords
{
    protected static string $resource = CreditLimitAcceptanceReportResource::class;
}
