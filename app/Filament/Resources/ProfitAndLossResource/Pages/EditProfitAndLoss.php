<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfitAndLossResource\Pages;

use App\Filament\Resources\ProfitAndLossResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditProfitAndLoss extends EditRecord
{
    /** @var class-string<ProfitAndLossResource> */
    protected static string $resource = ProfitAndLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ViewAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }
}
