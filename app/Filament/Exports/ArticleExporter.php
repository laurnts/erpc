<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Article;
use App\Models\Company;
use App\Models\SupplierArticle;
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
                ->formatStateUsing(fn (mixed $state): string => $state ? 'Yes' : 'No'),
            ExportColumn::make('show_in_product_grid')
                ->label('In Grid')
                ->formatStateUsing(fn (mixed $state): string => $state ? 'Yes' : 'No'),
            ExportColumn::make('supplier_code')
                ->label('Supplier Code')
                ->getStateUsing(fn (Article $record): string => self::primarySupplier($record)->code ?? ''),
            ExportColumn::make('supplier_name')
                ->label('Supplier Name')
                ->getStateUsing(fn (Article $record): string => self::primarySupplier($record)->name ?? ''),
            ExportColumn::make('supplier_sku')
                ->label('Supplier SKU')
                ->getStateUsing(fn (Article $record): string => self::primarySupplierLink($record)->supplier_sku ?? ''),
            ExportColumn::make('supplier_price')
                ->label('Supplier Price')
                ->getStateUsing(fn (Article $record): ?string => self::primarySupplierLink($record)?->supplier_price),
            ExportColumn::make('lead_time_days')
                ->label('Lead Time')
                ->getStateUsing(fn (Article $record): ?int => self::primarySupplierLink($record)?->lead_time_days),
            ExportColumn::make('supplier_is_preferred')
                ->label('Preferred')
                ->getStateUsing(function (Article $record): string {
                    $link = self::primarySupplierLink($record);

                    return $link instanceof \App\Models\SupplierArticle ? ($link->is_preferred ? 'Yes' : 'No') : ('');
                }),
            ExportColumn::make('supplier_is_active')
                ->label('Supplier Active')
                ->getStateUsing(function (Article $record): string {
                    $link = self::primarySupplierLink($record);

                    return $link instanceof \App\Models\SupplierArticle ? ($link->is_active ? 'Yes' : 'No') : ('');
                }),
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

    private static function primarySupplierLink(Article $article): ?SupplierArticle
    {
        return SupplierArticle::query()
            ->where('article_id', $article->getKey())
            ->orderByDesc('is_preferred')
            ->orderBy('id')
            ->first();
    }

    private static function primarySupplier(Article $article): ?Company
    {
        $link = self::primarySupplierLink($article);

        return $link?->supplier;
    }
}
