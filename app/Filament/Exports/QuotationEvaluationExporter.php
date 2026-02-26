<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\QuotationEvaluation;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

final class QuotationEvaluationExporter extends BaseExporter
{
    protected static ?string $model = QuotationEvaluation::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('qe_number')->label('QE Number'),
            ExportColumn::make('status')->formatStateUsing(fn (mixed $state): string => $state->value ?? (string) $state),
            ExportColumn::make('request.request_number')->label('Request'),
            ExportColumn::make('description'),
            ExportColumn::make('qe_date')->label('Date')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d') ?? ''),
            ExportColumn::make('team.name')->label('Team'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('created_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('updated_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your quotation evaluations export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
