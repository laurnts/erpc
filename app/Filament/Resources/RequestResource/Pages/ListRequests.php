<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;
use Override;
use Relaticle\CustomFields\Concerns\InteractsWithCustomFields;

final class ListRequests extends ListRecords
{
    use InteractsWithCustomFields;

    /** @var class-string<RequestResource> */
    protected static string $resource = RequestResource::class;

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
