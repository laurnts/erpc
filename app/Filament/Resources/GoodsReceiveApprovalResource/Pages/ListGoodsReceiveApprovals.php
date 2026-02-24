<?php

declare(strict_types=1);

namespace App\Filament\Resources\GoodsReceiveApprovalResource\Pages;

use App\Filament\Resources\GoodsReceiveApprovalResource;
use Filament\Resources\Pages\ListRecords;

final class ListGoodsReceiveApprovals extends ListRecords
{
    protected static string $resource = GoodsReceiveApprovalResource::class;
}
