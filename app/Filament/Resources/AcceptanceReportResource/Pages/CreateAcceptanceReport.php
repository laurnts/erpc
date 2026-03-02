<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcceptanceReportResource\Pages;

use App\Filament\Resources\AcceptanceReportResource;
use App\Models\AcceptanceReport;
use Filament\Resources\Pages\CreateRecord;

final class CreateAcceptanceReport extends CreateRecord
{
    /** @var class-string<AcceptanceReportResource> */
    protected static string $resource = AcceptanceReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate report number if not provided
        if (empty($data['report_number']) && ! empty($data['request_id'])) {
            $data['report_number'] = AcceptanceReport::generateReportNumber($data['request_id']);
        }

        return $data;
    }
}
