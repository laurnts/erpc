<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Jobs\FetchFaviconForCompany;
use App\Models\Company;
use Filament\Resources\Pages\CreateRecord;

final class CreateCompany extends CreateRecord
{
    /** @var class-string<CompanyResource> */
    protected static string $resource = CompanyResource::class;

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
