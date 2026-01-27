<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnitOfMeasureResource\Pages;

use App\Filament\Resources\UnitOfMeasureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;
use Override;

final class ListUnitOfMeasures extends ListRecords
{
    /** @var class-string<UnitOfMeasureResource> */
    protected static string $resource = UnitOfMeasureResource::class;

    /**
     * Get the actions available on the resource index header.
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->size(Size::Small)
                ->slideOver(),
        ];
    }
}
