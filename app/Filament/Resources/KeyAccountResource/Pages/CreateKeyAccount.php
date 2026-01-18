<?php

declare(strict_types=1);

namespace App\Filament\Resources\KeyAccountResource\Pages;

use App\Filament\Resources\KeyAccountResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateKeyAccount extends CreateRecord
{
    /** @var class-string<KeyAccountResource> */
    protected static string $resource = KeyAccountResource::class;
}
