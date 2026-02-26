<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Article;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use Relaticle\CustomFields\Facades\CustomFields;

final class ArticleExporter extends BaseExporter
{
    protected static ?string $model = Article::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('code'),
            ExportColumn::make('name'),
            ExportColumn::make('sku')->label('SKU'),
            ExportColumn::make('unitOfMeasure.label')->label('Unit'),
            ExportColumn::make('defaultTaxCode.name')->label('Tax Code'),
            ExportColumn::make('description'),
            ExportColumn::make('is_active')
                ->label('Active')
                ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
            ExportColumn::make('team.name')->label('Team'),
            ExportColumn::make('creator.name')->label('Created By'),
            ExportColumn::make('created_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('updated_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ExportColumn::make('deleted_at')->formatStateUsing(fn (?Carbon $state): string => $state?->format('Y-m-d H:i:s') ?? ''),
            ...CustomFields::exporter()->forModel(self::getModel())->columns(),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your articles export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
