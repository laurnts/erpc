<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\SupplierQuote;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

final class SupplierQuoteExporter extends BaseExporter
{
    protected static ?string $model = SupplierQuote::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('quote_number')->label('Quote #'),
            ExportColumn::make('request.request_number')->label('Request'),
            ExportColumn::make('supplier.name')->label('Supplier'),
            ExportColumn::make('supplier_reference')->label('Supplier Ref'),
            ExportColumn::make('status')->formatStateUsing(fn (mixed $state): string => $state->value ?? (string) $state),
            ExportColumn::make('currency.code')->label('Currency'),
            ExportColumn::make('total'),
            ExportColumn::make('total_base')->label('Total (Base)'),
            ExportColumn::make('valid_until')->label('Valid Until')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d') ?? ''),
            ExportColumn::make('team.name')->label('Team'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('created_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('updated_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your supplier quotes export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
