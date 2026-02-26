<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\ProfitAndLoss;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

final class ProfitAndLossExporter extends BaseExporter
{
    protected static ?string $model = ProfitAndLoss::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('pnl_number')->label('PNL Number'),
            ExportColumn::make('status')->formatStateUsing(fn (mixed $state): string => $state->value ?? (string) $state),
            ExportColumn::make('request.request_number')->label('Request'),
            ExportColumn::make('description'),
            ExportColumn::make('pnl_date')->label('Date')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d') ?? ''),
            ExportColumn::make('team.name')->label('Team'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('created_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('updated_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your profit & loss export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
