<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnitOfMeasureResource\Pages;

use App\Filament\Resources\UnitOfMeasureResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateUnitOfMeasure extends CreateRecord
{
    /** @var class-string<UnitOfMeasureResource> */
    protected static string $resource = UnitOfMeasureResource::class;
}
