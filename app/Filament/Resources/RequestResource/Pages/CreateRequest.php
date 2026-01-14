<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

final class CreateRequest extends CreateRecord
{
    /** @var class-string<RequestResource> */
    protected static string $resource = RequestResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(RequestResource::getFormSchema(isCreate: true))
            ->columns(1);
    }
}
