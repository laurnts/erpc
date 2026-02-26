<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Company;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Facades\CustomFields;

final class SupplierExporter extends BaseExporter
{
    protected static ?string $model = Company::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Company Name'),
            ExportColumn::make('domain')->label('Domain'),
            ExportColumn::make('team.name')->label('Team'),
            ExportColumn::make('accountOwner.name')->label('Account Owner'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('people_count')
                ->label('Number of People')
                ->state(fn (Company $company): int => $company->people()->count()),
            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('updated_at')
                ->label('Updated At')
                ->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('creation_source')
                ->label('Creation Source')
                ->formatStateUsing(fn (mixed $state): string => $state->value ?? (string) $state),
            ...CustomFields::exporter()->forModel(self::getModel())->columns(),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $query = parent::modifyQuery($query);

        return $query->where('is_supplier', true);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your suppliers export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
