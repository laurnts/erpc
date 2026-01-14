<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierQuoteResource\Pages;

use App\Filament\Resources\SupplierQuoteResource;
use Filament\Resources\Pages\ListRecords;

final class ListSupplierQuotes extends ListRecords
{
    protected static string $resource = SupplierQuoteResource::class;
}
