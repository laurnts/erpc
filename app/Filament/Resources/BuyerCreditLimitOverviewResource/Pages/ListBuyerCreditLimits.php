<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerCreditLimitOverviewResource\Pages;

use App\Filament\Resources\BuyerCreditLimitOverviewResource;
use Filament\Resources\Pages\ListRecords;

final class ListBuyerCreditLimits extends ListRecords
{
    protected static string $resource = BuyerCreditLimitOverviewResource::class;
}
