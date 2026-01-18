<?php

declare(strict_types=1);

namespace App\Filament\Resources\KeyAccountResource\Pages;

use App\Filament\Resources\KeyAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;
use Override;

final class ListKeyAccounts extends ListRecords
{
    /** @var class-string<KeyAccountResource> */
    protected static string $resource = KeyAccountResource::class;

    /**
     * Get the actions available on the resource index header.
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus')->size(Size::Small),
        ];
    }
}
