<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerQuoteResource\Pages;

use App\Filament\Resources\BuyerQuoteResource;
use Filament\Resources\Pages\ListRecords;

final class ListBuyerQuotes extends ListRecords
{
    protected static string $resource = BuyerQuoteResource::class;
}
