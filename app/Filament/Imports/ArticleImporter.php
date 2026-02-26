<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\Article;
use App\Models\UnitOfMeasure;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Relaticle\CustomFields\Facades\CustomFields;

final class ArticleImporter extends BaseImporter
{
    protected static ?string $model = Article::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->guess(['name', 'article_name', 'product_name'])
                ->rules(['required', 'string', 'max:255'])
                ->example('Widget A')
                ->fillRecordUsing(function (Article $record, string $state, Importer $importer): void {
                    $record->name = trim($state);
                    if (! $record->exists) {
                        $record->team_id = $importer->import->team_id;
                        $record->creator_id = $importer->import->user_id;
                        $record->unit = 'pcs';
                        $record->is_active = true;
                        $defaultUom = UnitOfMeasure::query()
                            ->where('team_id', $importer->import->team_id)
                            ->where('code', 'pcs')
                            ->where('is_active', true)
                            ->value('id');
                        if ($defaultUom) {
                            $record->unit_of_measure_id = $defaultUom;
                        }
                    }
                }),
            ImportColumn::make('sku')
                ->label('SKU')
                ->guess(['sku', 'code'])
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(function (Article $record, ?string $state): void {
                    $record->sku = $state ? trim($state) : null;
                }),
            ImportColumn::make('code')
                ->label('Article Code')
                ->guess(['code', 'article_code'])
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(function (Article $record, ?string $state): void {
                    if ($state !== null && $state !== '' && $record->exists === false) {
                        $record->code = trim($state);
                    }
                }),
            ...CustomFields::importer()->forModel(self::getModel())->columns(),
        ];
    }

    public function resolveRecord(): Article
    {
        $teamId = $this->import->team_id;
        $code = trim((string) ($this->data['code'] ?? ''));
        $name = trim((string) ($this->data['name'] ?? ''));

        if ($code !== '') {
            $article = Article::query()
                ->when($teamId, fn (Builder $q) => $q->where('team_id', $teamId))
                ->where('code', $code)
                ->first();
            if ($article !== null) {
                return $article;
            }
        }

        if ($name !== '') {
            $article = Article::query()
                ->when($teamId, fn (Builder $q) => $q->where('team_id', $teamId))
                ->where('name', $name)
                ->first();
            if ($article !== null) {
                return $article;
            }
        }

        return new Article;
    }

    protected function afterSave(): void
    {
        CustomFields::importer()->forModel($this->record)->saveValues();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your articles import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
