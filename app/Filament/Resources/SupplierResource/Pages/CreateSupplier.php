<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Jobs\FetchFaviconForCompany;
use App\Models\Company;
use Filament\Resources\Pages\CreateRecord;

final class CreateSupplier extends CreateRecord
{
    /** @var class-string<SupplierResource> */
    protected static string $resource = SupplierResource::class;

    protected function afterCreate(): void
    {
        /** @var Company $company */
        $company = $this->record;

        // Dispatch favicon fetch job if domain is provided
        if ($company->domain !== null && $company->domain !== '') {
            FetchFaviconForCompany::dispatch($company)->afterCommit();
        }
    }
}
