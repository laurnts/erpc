<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\BuyerOrder;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

final class BuyerOrderExporter extends BaseExporter
{
    protected static ?string $model = BuyerOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('order_number')->label('Order #'),
            ExportColumn::make('request.request_number')->label('Request'),
            ExportColumn::make('buyer.name')->label('Buyer'),
            ExportColumn::make('buyerQuote.quote_number')->label('From Quote'),
            ExportColumn::make('status')->formatStateUsing(fn (mixed $state): string => $state->value ?? (string) $state),
            ExportColumn::make('total'),
            ExportColumn::make('payment_terms_days')->label('Terms'),
            ExportColumn::make('ordered_at')->label('Ordered')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('confirmed_at')->label('Confirmed')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('team.name')->label('Team'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('created_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('updated_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your buyer orders export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
