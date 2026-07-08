<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Resources\BuyerRequestResource\Pages;

use App\Filament\Buyer\Resources\BuyerRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListBuyerRequests extends ListRecords
{
    protected static string $resource = BuyerRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Submit Request'),
        ];
    }
}
