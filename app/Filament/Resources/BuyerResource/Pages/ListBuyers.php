<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Filament\Resources\BuyerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListBuyers extends ListRecords
{
    protected static string $resource = BuyerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['is_buyer'] = true;

                    return $data;
                }),
        ];
    }
}
