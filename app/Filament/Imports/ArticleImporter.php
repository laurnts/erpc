<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Actions\SupplierArticles\SetPreferredSupplier;
use App\Models\Article;
use App\Models\Company;
use App\Models\SupplierArticle;
use App\Models\UnitOfMeasure;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
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
            ImportColumn::make('show_in_product_grid')
                ->label('In Grid')
                ->guess(['in_grid', 'in grid', 'show_in_product_grid'])
                ->boolean()
                ->ignoreBlankState()
                ->rules(['nullable', 'boolean'])
                ->example('Yes'),
            ImportColumn::make('supplier_code')
                ->label('Supplier Code')
                ->guess(['supplier_code', 'supplier code'])
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:255'])
                ->example('CMP-0007')
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('supplier_name')
                ->label('Supplier Name')
                ->guess(['supplier_name', 'supplier', 'supplier name'])
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:255'])
                ->example('Acme Supplies')
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('supplier_sku')
                ->label('Supplier SKU')
                ->guess(['supplier_sku', 'supplier sku'])
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('supplier_price')
                ->label('Supplier Price')
                ->guess(['supplier_price', 'supplier price'])
                ->numeric()
                ->ignoreBlankState()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('lead_time_days')
                ->label('Lead Time')
                ->guess(['lead_time_days', 'lead time', 'lead_time'])
                ->integer()
                ->ignoreBlankState()
                ->rules(['nullable', 'integer', 'min:0'])
                ->example('14')
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('supplier_is_preferred')
                ->label('Preferred')
                ->guess(['preferred', 'supplier_preferred', 'is_preferred'])
                ->boolean()
                ->ignoreBlankState()
                ->rules(['nullable', 'boolean'])
                ->example('No')
                ->fillRecordUsing(static function (): void {}),
            ImportColumn::make('supplier_is_active')
                ->label('Supplier Active')
                ->guess(['supplier_active', 'supplier is active'])
                ->boolean()
                ->ignoreBlankState()
                ->rules(['nullable', 'boolean'])
                ->example('Yes')
                ->fillRecordUsing(static function (): void {}),
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

    protected function beforeCreate(): void
    {
        $supplierCode = $this->resolveImportString('supplier_code', ['supplier code', 'supplier-code']);
        $supplierName = $this->resolveImportString('supplier_name', ['supplier', 'supplier name', 'supplier-name']);

        if ($supplierCode === '' && $supplierName === '') {
            throw ValidationException::withMessages([
                'supplier' => 'A supplier is required when importing a new article.',
            ]);
        }
    }

    protected function afterSave(): void
    {
        CustomFields::importer()->forModel($this->record)->saveValues();
        $this->linkSupplierIfProvided();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your articles import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    private function linkSupplierIfProvided(): void
    {
        $supplierCode = $this->resolveImportString('supplier_code', ['supplier code', 'supplier-code']);
        $supplierName = $this->resolveImportString('supplier_name', ['supplier', 'supplier name', 'supplier-name']);

        if ($supplierCode === '' && $supplierName === '') {
            return;
        }

        $supplier = $this->resolveSupplier($supplierCode, $supplierName);

        if (! $supplier instanceof \App\Models\Company) {
            $identifier = $supplierCode !== '' ? $supplierCode : $supplierName;

            throw ValidationException::withMessages([
                'supplier' => sprintf('Supplier "%s" was not found.', $identifier),
            ]);
        }

        /** @var Article $article */
        $article = $this->record;

        $linkExists = SupplierArticle::query()
            ->where('article_id', $article->getKey())
            ->where('supplier_id', $supplier->getKey())
            ->exists();

        $pivotData = $this->buildSupplierPivotData();

        if (! $linkExists) {
            $pivotData = array_merge([
                'is_active' => true,
                'is_preferred' => false,
            ], $pivotData);
        }

        $isPreferred = (bool) ($pivotData['is_preferred'] ?? false);
        unset($pivotData['is_preferred']);

        SupplierArticle::query()->updateOrCreate(
            [
                'article_id' => $article->getKey(),
                'supplier_id' => $supplier->getKey(),
            ],
            $pivotData,
        );

        if ($isPreferred) {
            app(SetPreferredSupplier::class)->execute($article->getKey(), $supplier->getKey());
        }
    }

    private function resolveSupplier(string $code, string $name): ?Company
    {
        $teamId = $this->import->team_id;

        $query = Company::query()
            ->when($teamId, fn (Builder $q) => $q->where('team_id', $teamId))
            ->where('is_supplier', true);

        if ($code !== '') {
            return (clone $query)->where('code', $code)->first();
        }

        if ($name !== '') {
            return (clone $query)->where('name', $name)->first();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSupplierPivotData(): array
    {
        $pivot = [];

        $supplierSku = $this->resolveImportString('supplier_sku', ['supplier sku', 'supplier-sku']);

        if ($supplierSku !== '') {
            $pivot['supplier_sku'] = $supplierSku;
        }

        $supplierPrice = $this->resolveImportString('supplier_price', ['supplier price', 'supplier-price']);

        if ($supplierPrice !== '') {
            $pivot['supplier_price'] = is_numeric($supplierPrice) ? (float) $supplierPrice : $supplierPrice;
        }

        $leadTimeDays = $this->resolveImportString('lead_time_days', ['lead time', 'lead-time']);

        if ($leadTimeDays !== '') {
            $pivot['lead_time_days'] = (int) $leadTimeDays;
        }

        $preferred = $this->resolveImportString('supplier_is_preferred', ['preferred']);

        if ($preferred !== '') {
            $pivot['is_preferred'] = $this->castImportBoolean($preferred);
        }

        $supplierActive = $this->resolveImportString('supplier_is_active', ['supplier active', 'supplier is active']);

        if ($supplierActive !== '') {
            $pivot['is_active'] = $this->castImportBoolean($supplierActive);
        }

        return $pivot;
    }

    private function castImportBoolean(string $value): bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'y', 'on' => true,
            default => false,
        };
    }

    /**
     * @param  array<int, string>  $additionalGuesses
     */
    private function resolveImportString(string $column, array $additionalGuesses = []): string
    {
        if (array_key_exists($column, $this->data)) {
            $value = trim((string) ($this->data[$column] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        $guesses = array_merge(
            [str_replace('_', ' ', $column)],
            $additionalGuesses,
        );

        foreach ($this->originalData as $header => $value) {
            $normalizedHeader = $this->normalizeImportHeader((string) $header);

            foreach ($guesses as $guess) {
                if ($normalizedHeader === $this->normalizeImportHeader($guess)) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    private function normalizeImportHeader(string $header): string
    {
        return (string) str($header)
            ->lower()
            ->replace('-', ' ')
            ->replace('_', ' ')
            ->squish();
    }
}
