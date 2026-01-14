<?php

declare(strict_types=1);

namespace App\Filament\Resources\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCurrency extends CreateRecord
{
    /** @var class-string<CurrencyResource> */
    protected static string $resource = CurrencyResource::class;
}
