<?php

declare(strict_types=1);

namespace App\Filament\Resources\PeopleResource\Pages;

use App\Filament\Resources\PeopleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePeople extends CreateRecord
{
    /** @var class-string<PeopleResource> */
    protected static string $resource = PeopleResource::class;
}
