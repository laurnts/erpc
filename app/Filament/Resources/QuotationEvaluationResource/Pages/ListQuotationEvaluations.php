<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuotationEvaluationResource\Pages;

use App\Filament\Exports\QuotationEvaluationExporter;
use App\Filament\Resources\QuotationEvaluationResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

final class ListQuotationEvaluations extends ListRecords
{
    /** @var class-string<QuotationEvaluationResource> */
    protected static string $resource = QuotationEvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()->exporter(QuotationEvaluationExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Export')
                ->size(Size::Small),
        ];
    }
}
