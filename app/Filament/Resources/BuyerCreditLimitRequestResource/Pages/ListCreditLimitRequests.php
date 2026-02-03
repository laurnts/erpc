<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerCreditLimitRequestResource\Pages;

use App\Filament\Resources\BuyerCreditLimitRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListCreditLimitRequests extends ListRecords
{
    protected static string $resource = BuyerCreditLimitRequestResource::class;
}
