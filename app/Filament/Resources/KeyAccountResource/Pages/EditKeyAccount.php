<?php

declare(strict_types=1);

namespace App\Filament\Resources\KeyAccountResource\Pages;

use App\Filament\Resources\KeyAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditKeyAccount extends EditRecord
{
    /** @var class-string<KeyAccountResource> */
    protected static string $resource = KeyAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
