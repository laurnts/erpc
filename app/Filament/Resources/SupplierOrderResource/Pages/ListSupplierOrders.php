<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOrderResource\Pages;

use App\Filament\Resources\SupplierOrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListSupplierOrders extends ListRecords
{
    protected static string $resource = SupplierOrderResource::class;
}
