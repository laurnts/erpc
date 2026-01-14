<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateProject extends CreateRecord
{
    /** @var class-string<ProjectResource> */
    protected static string $resource = ProjectResource::class;
}
