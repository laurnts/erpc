<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuotationEvaluationResource\Pages;

use App\Filament\Resources\QuotationEvaluationResource;
use Filament\Resources\Pages\ListRecords;

final class ListQuotationEvaluations extends ListRecords
{
    /** @var class-string<QuotationEvaluationResource> */
    protected static string $resource = QuotationEvaluationResource::class;
}
