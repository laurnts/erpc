<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\Pages;

use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Models\RequestItem;
use App\Models\UnitOfMeasure;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

final class EditCustomerRequest extends EditRecord
{
    protected static string $resource = CustomerRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->getRecord()->isEditableByCustomer()) {
            $this->redirect(CustomerRequestResource::getUrl('view', ['record' => $this->getRecord()]));
        }

        $this->form->fill([
            ...$this->getRecord()->attributesToArray(),
            'items' => $this->getRecord()->items->map(fn (RequestItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_of_measure_id' => $item->unit_of_measure_id,
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(CreateCustomerRequest::formComponents())
            ->columns(1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['items']);

        return $data;
    }

    protected function afterSave(): void
    {
        $items = $this->form->getState()['items'] ?? [];

        $this->getRecord()->items()->delete();

        foreach ($items as $index => $item) {
            $uom = UnitOfMeasure::query()->find($item['unit_of_measure_id']);

            RequestItem::query()->create([
                'request_id' => $this->getRecord()->getKey(),
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_of_measure_id' => $item['unit_of_measure_id'],
                'unit' => $uom?->code ?? 'pcs',
                'sort_order' => $index,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return CustomerRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
