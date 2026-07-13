<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerCreditLimitOverviewResource\Pages;

use App\Filament\Resources\BuyerCreditLimitOverviewResource;
use App\Models\Company;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

final class ViewBuyerCreditLimit extends ViewRecord
{
    protected static string $resource = BuyerCreditLimitOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Eager load defaultCurrency relationship
        $this->record->load('defaultCurrency');

        return $data;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Grid::make(4)
                    ->schema([
                        TextEntry::make('name')
                            ->label('')
                            ->size(TextSize::Large)
                            ->weight('bold'),
                        TextEntry::make('code')
                            ->label('Code')
                            ->size(TextSize::Large),
                    ]),
                Grid::make(4)
                    ->schema([
                        TextEntry::make('credit_limit')
                            ->label('Max Credit Limit')
                            ->formatStateUsing(function (mixed $state, Company $record): string {
                                /** @var \App\Models\Company $record */
                                $currency = $record->defaultCurrency
                                    ?? (\Filament\Facades\Filament::getTenant() instanceof \App\Models\Team
                                        ? \Filament\Facades\Filament::getTenant()->getBaseCurrency()
                                        : null);

                                return \App\Models\Currency::formatAmount((float) $state, $currency);
                            }),
                        TextEntry::make('available_credit')
                            ->label('Available Credit')
                            ->formatStateUsing(function (mixed $state, Company $record): string {
                                /** @var \App\Models\Company $record */
                                $currency = $record->defaultCurrency
                                    ?? (\Filament\Facades\Filament::getTenant() instanceof \App\Models\Team
                                        ? \Filament\Facades\Filament::getTenant()->getBaseCurrency()
                                        : null);

                                return \App\Models\Currency::formatAmount((float) $state, $currency);
                            }),
                        TextEntry::make('credit_used')
                            ->label('Credit Used')
                            ->formatStateUsing(function (mixed $state, Company $record): string {
                                /** @var \App\Models\Company $record */
                                $currency = $record->defaultCurrency
                                    ?? (\Filament\Facades\Filament::getTenant() instanceof \App\Models\Team
                                        ? \Filament\Facades\Filament::getTenant()->getBaseCurrency()
                                        : null);

                                return \App\Models\Currency::formatAmount((float) $state, $currency);
                            }),
                        TextEntry::make('credit_status')
                            ->label('Credit Status')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                    ]),
            ])
                ->columnSpanFull(),

            Section::make('Credit Usage History')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    ViewEntry::make('credit_usage_history')
                        ->label('')
                        ->view('filament.infolists.components.credit-usage-history'),
                ])
                ->collapsible()
                ->collapsed(false)
                ->columnSpanFull(),
        ]);
    }
}
