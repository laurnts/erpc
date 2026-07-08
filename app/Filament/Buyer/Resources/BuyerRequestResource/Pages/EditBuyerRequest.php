<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Resources\BuyerRequestResource\Pages;

use App\Filament\Buyer\Resources\BuyerRequestResource;
use App\Filament\Buyer\Resources\BuyerRequestResource\Schemas\BuyerRequestForm;
use App\Models\RequestItem;
use App\Models\UnitOfMeasure;
use App\Support\LineItemReconciler;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

final class EditBuyerRequest extends EditRecord
{
    protected static string $resource = BuyerRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->getRecord()->isEditableByBuyer()) {
            $this->redirect(BuyerRequestResource::getUrl('view', ['record' => $this->getRecord()]));
        }

        $this->form->fill([
            ...$this->getRecord()->attributesToArray(),
            'items' => $this->getRecord()->items->map(fn (RequestItem $item): array => [
                'id' => $item->getKey(),
                'description' => $item->description,
                'item_type' => $item->item_type->value,
                'quantity' => $item->quantity,
                'unit_of_measure_id' => $item->unit_of_measure_id,
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(BuyerRequestForm::components())
            ->columns(1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['items'], $data['attachment_files'], $data['submission_method_choice']);

        return $data;
    }

    protected function afterSave(): void
    {
        /**
         * Read the raw component state (not getState()) so each row keeps the
         * hidden `id` seeded in mount(); getState() dehydrates only the visible
         * repeater fields and would strip it. The id lets the reconciler match
         * surviving rows in place so a genuine edit fires `updated`/`deleted`
         * instead of a full delete-and-recreate (design D2).
         *
         * @var array<int|string, array<string, mixed>> $items
         */
        $items = $this->data['items'] ?? [];

        LineItemReconciler::reconcile(
            $this->getRecord()->items(),
            $items,
            function (array $item, int $index): array {
                $uom = UnitOfMeasure::query()->find($item['unit_of_measure_id']);

                return [
                    'description' => $item['description'],
                    'item_type' => $item['item_type'] ?? \App\Enums\ItemType::GOODS,
                    'quantity' => $item['quantity'],
                    'unit_of_measure_id' => $item['unit_of_measure_id'],
                    'unit' => $uom?->code ?? 'pcs',
                    'sort_order' => $index,
                ];
            },
        );
    }

    protected function getRedirectUrl(): string
    {
        return BuyerRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
