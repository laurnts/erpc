<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierArticleResource\Pages;

use App\Filament\Supplier\Resources\SupplierArticleResource;
use Filament\Resources\Pages\ListRecords;

final class ListSupplierArticles extends ListRecords
{
    protected static string $resource = SupplierArticleResource::class;

    /**
     * Listings are staff-owned: no create, attach, or delete actions exist
     * anywhere in the supplier panel.
     *
     * @return array<mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
