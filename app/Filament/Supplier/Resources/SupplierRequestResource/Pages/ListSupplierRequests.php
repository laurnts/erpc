<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierRequestResource\Pages;

use App\Filament\Supplier\Resources\SupplierRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListSupplierRequests extends ListRecords
{
    protected static string $resource = SupplierRequestResource::class;
}
