<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Enums\DeliveryType;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupplierResource\RelationManagers\ArticlesRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Relaticle\CustomFields\Facades\CustomFields;

final class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Flex::make([
                    Section::make('Supplier Information')
                        ->schema([
                            TextEntry::make('code')
                                ->label('Code')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('name')
                                ->label('Company Name')
                                ->size(TextSize::Large),
                            TextEntry::make('domain')
                                ->label('Domain')
                                ->placeholder('—')
                                ->url(fn ($record) => $record->domain ? "https://{$record->domain}" : null)
                                ->openUrlInNewTab(),
                            TextEntry::make('email')
                                ->label('Email')
                                ->placeholder('—')
                                ->copyable()
                                ->icon('heroicon-o-envelope'),
                            TextEntry::make('phone')
                                ->label('Phone')
                                ->placeholder('—')
                                ->copyable()
                                ->icon('heroicon-o-phone'),
                        ])
                        ->columns(2),
                    Section::make('Location')
                        ->schema([
                            TextEntry::make('country')
                                ->label('Country')
                                ->placeholder('—'),
                            TextEntry::make('address')
                                ->label('Address')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->grow(false),
                ])->columnSpan('full'),

                Section::make('Financial Settings')
                    ->schema([
                        TextEntry::make('defaultCurrency.name')
                            ->label('Default Currency')
                            ->placeholder('—'),
                        TextEntry::make('payment_terms_days')
                            ->label('Default Payment Terms')
                            ->suffix(' days')
                            ->placeholder('—'),
                        TextEntry::make('lead_time_days')
                            ->label('Default Lead Time')
                            ->suffix(' days')
                            ->placeholder('—'),
                        TextEntry::make('delivery_type')
                            ->label('Delivery Type')
                            ->formatStateUsing(fn ($state): ?string => $state instanceof DeliveryType ? $state->getLabel() : ($state ?? null))
                            ->placeholder('—'),
                        TextEntry::make('delivery_type_details')
                            ->label('Delivery Type Details')
                            ->placeholder('—'),
                        IconEntry::make('is_taxable')
                            ->label('Taxable Company')
                            ->boolean(),
                        TextEntry::make('delivery_term')
                            ->label('Delivery Term')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan('full'),

                Section::make('Additional Information')
                    ->schema([
                        CustomFields::infolist()->forSchema($schema)->build(),
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columnSpan('full'),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            ArticlesRelationManager::class,
        ];
    }
}
