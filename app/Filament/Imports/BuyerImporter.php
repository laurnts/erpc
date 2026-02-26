<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Enums\CreationSource;
use App\Models\Company;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Relaticle\CustomFields\Facades\CustomFields;

final class BuyerImporter extends BaseImporter
{
    protected static ?string $model = Company::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->guess(['name', 'company_name', 'buyer'])
                ->rules(['required', 'string', 'max:255'])
                ->example('Acme Corp')
                ->fillRecordUsing(function (Company $record, string $state, Importer $importer): void {
                    $record->name = trim($state);
                    if (! $record->exists) {
                        $record->team_id = $importer->import->team_id;
                        $record->creator_id = $importer->import->user_id;
                        $record->creation_source = CreationSource::IMPORT;
                        $record->is_buyer = true;
                    }
                }),
            ImportColumn::make('domain')
                ->label('Domain')
                ->guess(['domain', 'website'])
                ->rules(['nullable', 'string', 'max:255'])
                ->example('example.com')
                ->fillRecordUsing(function (Company $record, ?string $state): void {
                    $record->domain = $state ? trim($state) : null;
                }),
            ...CustomFields::importer()->forModel(self::getModel())->columns(),
        ];
    }

    public function resolveRecord(): Company
    {
        $name = trim((string) ($this->data['name'] ?? ''));
        if ($name === '') {
            return new Company;
        }

        $company = Company::query()
            ->when($this->import->team_id, fn (Builder $q) => $q->where('team_id', $this->import->team_id))
            ->where('is_buyer', true)
            ->where('name', $name)
            ->first();

        return $company ?? new Company;
    }

    protected function afterSave(): void
    {
        CustomFields::importer()->forModel($this->record)->saveValues();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your buyers import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
