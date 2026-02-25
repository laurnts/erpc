<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOrderApprovals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SupplierOrderApprovalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Order Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('po_number')
                                    ->label('PO Number'),
                                TextEntry::make('approval_status')
                                    ->label('Status')
                                    ->getStateUsing(function ($record): string {
                                        $bothApproved = $record->approver_1_id !== null && $record->approver_2_id !== null;
                                        return $bothApproved ? 'Approved' : 'Pending';
                                    })
                                    ->badge()
                                    ->color(function ($record): string {
                                        $bothApproved = $record->approver_1_id !== null && $record->approver_2_id !== null;
                                        return $bothApproved ? 'success' : 'warning';
                                    }),
                                TextEntry::make('request.request_number')
                                    ->label('Request')
                                    ->url(fn ($record): string => \App\Filament\Resources\RequestResource::getUrl('view', ['record' => $record->request_id])),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('supplier.name')
                                    ->label('Supplier'),
                                TextEntry::make('currency.code')
                                    ->label('Currency'),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->formatStateUsing(fn ($record): string => $record->formatted_total),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('confirmed_at')
                                    ->label('Confirmed At')
                                    ->dateTime(),
                                TextEntry::make('expected_delivery_date')
                                    ->label('Expected Delivery')
                                    ->date(),
                                TextEntry::make('payment_terms_days')
                                    ->label('Payment Terms')
                                    ->formatStateUsing(fn ($record): ?string => $record->payment_terms_days ? "Net {$record->payment_terms_days} days" : null),
                            ]),
                    ]),
                Section::make('Approval Status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('approver_1')
                                    ->label('Approver 1')
                                    ->getStateUsing(function ($record): string {
                                        if ($record->approver_1_id === null) {
                                            return 'Pending';
                                        }
                                        $record->loadMissing('approver1');
                                        return $record->approver1->name ?? 'Unknown';
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => $record->approver_1_id === null ? 'warning' : 'success'),
                                TextEntry::make('approver_2')
                                    ->label('Approver 2')
                                    ->getStateUsing(function ($record): string {
                                        if ($record->approver_2_id === null) {
                                            return 'Pending';
                                        }
                                        $record->loadMissing('approver2');
                                        return $record->approver2->name ?? 'Unknown';
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => $record->approver_2_id === null ? 'warning' : 'success'),
                            ]),
                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime()
                            ->visible(fn ($record): bool => $record->approved_at !== null),
                    ]),
                Section::make('Items')
                    ->schema([
                        ViewEntry::make('items')
                            ->label('')
                            ->view('filament.infolists.components.supplier-order-items'),
                    ])
                    ->collapsible(),
                Section::make('Documents')
                    ->schema([
                        ViewEntry::make('documents')
                            ->label('')
                            ->view('filament.infolists.components.document-list'),
                    ])
                    ->collapsible(),
            ]);
    }
}
