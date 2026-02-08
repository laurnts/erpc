<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOrderApprovals\Pages;

use App\Filament\Resources\SupplierOrderApprovals\SupplierOrderApprovalResource;
use Filament\Resources\Pages\ListRecords;

final class ListSupplierOrderApprovals extends ListRecords
{
    protected static string $resource = SupplierOrderApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
