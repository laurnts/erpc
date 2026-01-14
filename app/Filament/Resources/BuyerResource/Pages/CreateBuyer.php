<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Filament\Resources\BuyerResource;
use App\Jobs\FetchFaviconForCompany;
use App\Models\Company;
use Filament\Resources\Pages\CreateRecord;

final class CreateBuyer extends CreateRecord
{
    /** @var class-string<BuyerResource> */
    protected static string $resource = BuyerResource::class;

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
