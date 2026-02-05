<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Filament\Resources\BuyerResource;
use Filament\Resources\Pages\EditRecord;

final class EditBuyer extends EditRecord
{
    protected static string $resource = BuyerResource::class;
}
