<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcceptanceReportResource\Pages;

use App\Filament\Resources\AcceptanceReportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditAcceptanceReport extends EditRecord
{
    /** @var class-string<AcceptanceReportResource> */
    protected static string $resource = AcceptanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
