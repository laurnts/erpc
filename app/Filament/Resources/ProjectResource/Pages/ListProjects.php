<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;
use Override;
use Relaticle\CustomFields\Concerns\InteractsWithCustomFields;

final class ListProjects extends ListRecords
{
    use InteractsWithCustomFields;

    /** @var class-string<ProjectResource> */
    protected static string $resource = ProjectResource::class;

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
