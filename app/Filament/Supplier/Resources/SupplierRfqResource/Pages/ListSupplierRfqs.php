<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierRfqResource\Pages;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Supplier\Resources\SupplierRfqResource;
use App\Models\SupplierQuote;
use Closure;
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
            'open' => $this->makeTab('Open', self::openTabQuery(...), 'danger'),
            'submitted' => $this->makeTab('Submitted', self::submittedTabQuery(...), 'primary'),
            'won' => $this->makeTab('Won', self::wonTabQuery(...), 'success'),
            'lost' => $this->makeTab('Lost', self::lostTabQuery(...), 'gray'),
            'declined' => $this->makeTab('Declined', self::declinedTabQuery(...), 'gray'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'open';
    }

    /**
     * @param  Closure(Builder<SupplierQuote>): Builder<SupplierQuote>  $modifyQuery
     */
    private function makeTab(string $label, Closure $modifyQuery, string $badgeColor): Tab
    {
        return Tab::make($label)
            ->modifyQueryUsing($modifyQuery)
            ->badge(fn (): ?int => $this->countForTab($modifyQuery))
            ->badgeColor($badgeColor);
    }

    /**
     * @param  Closure(Builder<SupplierQuote>): Builder<SupplierQuote>  $modifyQuery
     */
    private function countForTab(Closure $modifyQuery): ?int
    {
        /** @var Builder<SupplierQuote> $query */
        $query = static::getResource()::getEloquentQuery();

        $count = $modifyQuery($query)->count();

        return $count > 0 ? $count : null;
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function openTabQuery(Builder $query): Builder
    {
        return $query
            ->where('status', SupplierQuoteStatus::PENDING)
            ->whereNull('declined_at')
            ->where(fn (Builder $inner): Builder => $inner
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>', today()));
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function submittedTabQuery(Builder $query): Builder
    {
        return $query
            ->whereNull('outcomes_announced_at')
            ->where(fn (Builder $inner): Builder => $inner
                ->whereNotNull('submitted_at')
                ->orWhereIn('status', [
                    SupplierQuoteStatus::RECEIVED,
                    SupplierQuoteStatus::SELECTED,
                    SupplierQuoteStatus::REJECTED,
                ]));
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function wonTabQuery(Builder $query): Builder
    {
        return $query
            ->whereNotNull('outcomes_announced_at')
            ->whereIn('status', [
                SupplierQuoteStatus::SELECTED,
                SupplierQuoteStatus::RECEIVED,
            ]);
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function lostTabQuery(Builder $query): Builder
    {
        return $query
            ->whereNotNull('outcomes_announced_at')
            ->where('status', SupplierQuoteStatus::REJECTED);
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function declinedTabQuery(Builder $query): Builder
    {
        return $query->whereNotNull('declined_at');
    }
}
