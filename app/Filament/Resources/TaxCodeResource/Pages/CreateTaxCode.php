<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxCodeResource\Pages;

use App\Filament\Resources\TaxCodeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTaxCode extends CreateRecord
{
    /** @var class-string<TaxCodeResource> */
    protected static string $resource = TaxCodeResource::class;
}
