<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxCodeResource\Pages;

use App\Filament\Resources\TaxCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;
use Override;

final class ListTaxCodes extends ListRecords
{
    /** @var class-string<TaxCodeResource> */
    protected static string $resource = TaxCodeResource::class;

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
