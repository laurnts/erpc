<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfitAndLossResource\Pages;

use App\Filament\Resources\ProfitAndLossResource;
use Filament\Resources\Pages\ListRecords;

final class ListProfitAndLosses extends ListRecords
{
    /** @var class-string<ProfitAndLossResource> */
    protected static string $resource = ProfitAndLossResource::class;
}
