<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierRfqResource\Pages;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Supplier\Resources\SupplierRfqResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListSupplierRfqs extends ListRecords
{
    protected static string $resource = SupplierRfqResource::class;

    /**
     * Won/Lost populate only from announced outcomes: pre-announcement
     * evaluation churn keeps every submitted quote in "Submitted" regardless
     * of internal SELECTED/RECEIVED/REJECTED state.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', SupplierQuoteStatus::PENDING)
                    ->whereNull('declined_at')
                    ->where(fn (Builder $inner): Builder => $inner
                        ->whereNull('valid_until')
                        ->orWhereDate('valid_until', '>', today()))),
            'submitted' => Tab::make('Submitted')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNull('outcomes_announced_at')
                    ->where(fn (Builder $inner): Builder => $inner
                        ->whereNotNull('submitted_at')
                        ->orWhereIn('status', [
                            SupplierQuoteStatus::RECEIVED,
                            SupplierQuoteStatus::SELECTED,
                            SupplierQuoteStatus::REJECTED,
                        ]))),
            'won' => Tab::make('Won')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('outcomes_announced_at')
                    ->whereIn('status', [
                        SupplierQuoteStatus::SELECTED,
                        SupplierQuoteStatus::RECEIVED,
                    ])),
            'lost' => Tab::make('Lost')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('outcomes_announced_at')
                    ->where('status', SupplierQuoteStatus::REJECTED)),
            'declined' => Tab::make('Declined')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('declined_at')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'open';
    }
}
