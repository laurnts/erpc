<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\BuyerResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\RequestResource;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\RequestActivitiesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\Request;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Relaticle\CustomFields\Facades\CustomFields;

final class ViewRequest extends ViewRecord
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                RestoreAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            // Request Header Section
            Section::make()
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('request_number')
                                ->label('Request #')
                                ->weight('bold')
                                ->size('lg')
                                ->copyable(),
                            TextEntry::make('stage')
                                ->badge(),
                            TextEntry::make('priority')
                                ->badge(),
                            IconEntry::make('is_active')
                                ->label('Active')
                                ->boolean(),
                        ]),
                    TextEntry::make('title')
                        ->label('')
                        ->size('lg')
                        ->columnSpanFull(),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('buyer.name')
                                ->label('Buyer')
                                ->icon('heroicon-o-user-group')
                                ->color('primary')
                                ->url(fn (Request $record): ?string => $record->buyer ? BuyerResource::getUrl('index') : null),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('buyer.email')
                                ->label('Email')
                                ->icon('heroicon-o-envelope')
                                ->copyable()
                                ->visible(fn (Request $record): bool => $record->buyer?->email !== null),
                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->since(),
                        ]),
                ])
                ->columnSpanFull(),

            // Stage Progress Bar
            Section::make('Stage Progress')
                ->schema([
                    ViewEntry::make('stage_progress')
                        ->view('filament.infolists.components.request-stage-progress')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),

            // Financial Summary & Quick Actions (side by side)
            Grid::make(2)
                ->schema([
                    Section::make('Financial Summary')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('buyer_total_display')
                                        ->label('Buyer Total')
                                        ->state(fn (Request $record): string => $this->formatCurrency($record->buyer_total))
                                        ->color('success'),
                                    TextEntry::make('supplier_cost_display')
                                        ->label('Supplier Costs')
                                        ->state(fn (Request $record): string => $this->formatCurrency($record->supplier_cost))
                                        ->color('warning'),
                                    TextEntry::make('gross_margin_display')
                                        ->label('Gross Margin')
                                        ->state(fn (Request $record): string => $this->formatCurrency($record->gross_margin))
                                        ->color(fn (Request $record): string => $record->gross_margin > 0 ? 'success' : 'danger'),
                                    TextEntry::make('margin_percent_display')
                                        ->label('Margin %')
                                        ->state(fn (Request $record): string => $this->formatPercentage($record->margin_percent))
                                        ->badge()
                                        ->color(fn (Request $record): string => $record->margin_percent >= 10 ? 'success' : ($record->margin_percent >= 5 ? 'warning' : 'danger')),
                                ]),
                        ]),
                    Section::make('Quick Actions')
                        ->icon('heroicon-o-bolt')
                        ->schema([
                            TextEntry::make('quick_actions_hint')
                                ->label('')
                                ->state('Use the action menu above for:')
                                ->color('gray'),
                            TextEntry::make('quick_actions_list')
                                ->label('')
                                ->state('• Create/Revise Quotes
• Extend Quote Validity
• Resend to Buyer
• Mark Accepted/Rejected')
                                ->markdown()
                                ->color('gray'),
                        ]),
                ])
                ->columnSpanFull(),

            // Items Summary Card
            Section::make('Items Summary')
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('items_count')
                                ->label('Total Items')
                                ->state(fn (Request $record): int => $record->items()->count()),
                            TextEntry::make('matched_items_count')
                                ->label('Matched')
                                ->state(fn (Request $record): int => $record->items()->where('is_matched', true)->count())
                                ->color('success'),
                            TextEntry::make('unmatched_items_count')
                                ->label('Unmatched')
                                ->state(fn (Request $record): int => $record->items()->where('is_matched', false)->count())
                                ->color(fn (Request $record): string => $record->items()->where('is_matched', false)->exists() ? 'warning' : 'gray'),
                            IconEntry::make('all_items_matched')
                                ->label('Ready for Quoting')
                                ->state(fn (Request $record): bool => $record->all_items_matched)
                                ->boolean(),
                        ]),
                ])
                ->collapsible()
                ->columnSpanFull(),

            // Description (if exists)
            Section::make('Description')
                ->schema([
                    TextEntry::make('description')
                        ->label('')
                        ->markdown()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Request $record): bool => $record->description !== null)
                ->columnSpanFull(),

            // Project Link (if exists)
            Section::make('Project')
                ->schema([
                    TextEntry::make('project.name')
                        ->label('Linked Project')
                        ->icon('heroicon-o-folder')
                        ->color('primary')
                        ->url(fn (Request $record): ?string => $record->project ? ProjectResource::getUrl('index') : null),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Request $record): bool => $record->project_id !== null)
                ->columnSpanFull(),

            // Internal Notes
            Section::make('Internal Notes')
                ->icon('heroicon-o-document-text')
                ->schema([
                    TextEntry::make('internal_notes')
                        ->label('')
                        ->placeholder('No internal notes')
                        ->markdown()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),

            // Custom Fields
            CustomFields::infolist()->forSchema($schema)->build()->columnSpanFull(),
        ]);
    }

    public function getRelationManagers(): array
    {
        return [
            ItemsRelationManager::class,
            SupplierQuotesRelationManager::class,
            BuyerQuotesRelationManager::class,
            BuyerOrdersRelationManager::class,
            SupplierOrdersRelationManager::class,
            ShipmentsRelationManager::class,
            RequestActivitiesRelationManager::class,
        ];
    }

    /**
     * Format a currency value.
     */
    private function formatCurrency(float $value): string
    {
        if ($value === 0.0) {
            return '-';
        }

        return '$'.number_format($value, 2);
    }

    /**
     * Format a percentage value.
     */
    private function formatPercentage(float $value): string
    {
        return number_format($value, 1).'%';
    }
}
