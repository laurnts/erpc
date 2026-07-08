<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers;

use App\Enums\ShipmentType;
use App\Filament\Actions\DownloadPdfAction;
use App\Models\Request;
use App\Models\Shipment;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Shipments';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-truck';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Request $ownerRecord */
        if (! $ownerRecord->requiresShipments()) {
            return false;
        }

        return $ownerRecord->shipments()
            ->where('type', ShipmentType::OUTBOUND)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('type', ShipmentType::OUTBOUND)
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('shipment_number')
                    ->label('Shipment No.')
                    ->weight('bold'),
                TextColumn::make('do_number')
                    ->label('DO No.')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('carrier_name')
                    ->label('Carrier')
                    ->placeholder('-'),
                TextColumn::make('tracking_number')
                    ->label('Tracking No.')
                    ->placeholder('-')
                    ->copyable(),
                TextColumn::make('shipped_at')
                    ->label('Shipped')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('expected_delivery_at')
                    ->label('ETA')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->recordActions([
                DownloadPdfAction::make()
                    ->label('DO PDF')
                    ->authorize(fn (Shipment $record): bool => ($user = Filament::auth()->user()) !== null && $user->can('view', $record)),
                ViewAction::make()
                    ->modalHeading('Shipment Details')
                    ->schema(fn (Shipment $record): array => $this->getShipmentDetailSchema($record)),
            ])
            ->emptyStateHeading('No shipments yet')
            ->emptyStateDescription('Shipments will appear once your order has been shipped.');
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Infolists\Components\Entry>
     */
    private function getShipmentDetailSchema(Shipment $record): array
    {
        return [
            Section::make('Shipment Information')
                ->schema([
                    TextEntry::make('shipment_number')
                        ->label('Shipment No.')
                        ->state($record->shipment_number),
                    TextEntry::make('do_number')
                        ->label('Delivery Order No. (DO)')
                        ->state($record->do_number)
                        ->placeholder('-'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->state($record->status)
                        ->badge(),
                    TextEntry::make('carrier_name')
                        ->label('Carrier')
                        ->state($record->carrier_name)
                        ->placeholder('-'),
                    TextEntry::make('tracking_number')
                        ->label('Tracking No.')
                        ->state($record->tracking_number)
                        ->placeholder('-'),
                    TextEntry::make('shipped_at')
                        ->label('Shipped Date')
                        ->state($record->shipped_at)
                        ->dateTime()
                        ->placeholder('-'),
                    TextEntry::make('expected_delivery_at')
                        ->label('ETA')
                        ->state($record->expected_delivery_at)
                        ->dateTime()
                        ->placeholder('-'),
                    TextEntry::make('delivered_at')
                        ->label('Delivered Date')
                        ->state($record->delivered_at)
                        ->dateTime()
                        ->placeholder('-'),
                    TextEntry::make('do_sent_at')
                        ->label('DO Sent')
                        ->state($record->do_sent_at)
                        ->dateTime()
                        ->placeholder('-')
                        ->visible(fn (): bool => $record->do_sent_at !== null),
                ])
                ->columns(2),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
