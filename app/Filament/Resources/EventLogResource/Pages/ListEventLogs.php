<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventLogResource\Pages;

use App\Filament\Resources\EventLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventLogs extends ListRecords
{
    /** @var class-string<EventLogResource> */
    protected static string $resource = EventLogResource::class;
}
