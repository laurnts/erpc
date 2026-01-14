<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerOrderResource\Pages;

use App\Filament\Resources\BuyerOrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListBuyerOrders extends ListRecords
{
    protected static string $resource = BuyerOrderResource::class;
}
