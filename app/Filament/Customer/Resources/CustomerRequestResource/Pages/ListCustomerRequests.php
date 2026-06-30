<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\Pages;

use App\Filament\Customer\Resources\CustomerRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCustomerRequests extends ListRecords
{
    protected static string $resource = CustomerRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Submit Request'),
        ];
    }
}
