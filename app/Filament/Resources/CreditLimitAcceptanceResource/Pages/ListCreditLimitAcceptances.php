<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditLimitAcceptanceResource\Pages;

use App\Filament\Resources\CreditLimitAcceptanceResource;
use Filament\Resources\Pages\ListRecords;

final class ListCreditLimitAcceptances extends ListRecords
{
    protected static string $resource = CreditLimitAcceptanceResource::class;
}
