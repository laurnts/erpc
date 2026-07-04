<?php

declare(strict_types=1);

namespace App\Filament\Resources\PortalRegistrationRequestResource\Pages;

use App\Filament\Resources\PortalRegistrationRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListPortalRegistrationRequests extends ListRecords
{
    protected static string $resource = PortalRegistrationRequestResource::class;
}
