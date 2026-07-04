<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierArticleResource\Schemas;

use App\Models\Currency;
use App\Services\Portal\SupplierPortalContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * The supplier-facing offer form. Contains EXACTLY the four supplier-writable
 * fields; everything else is a read-only entry. The action-level whitelist in
 * UpdateSupplierArticleOffer is the enforcement against tampered payloads —
 * this form's field set is only the first layer.
 */
final readonly class SupplierArticleForm
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Section::make('Article')
                ->schema([
                    TextEntry::make('article.name')
                        ->label('Article Name'),
                    TextEntry::make('article.unit')
                        ->label('Unit'),
                ])
                ->columns(2),
            Section::make('Your Offer')
                ->schema([
                    TextInput::make('supplier_price')
                        ->label('Price')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Your standing offer price per unit'),
                    Select::make('supplier_price_currency_id')
                        ->label('Price Currency')
                        ->options(fn (): array => Currency::query()->where('is_active', true)->pluck('code', 'id')->all())
                        ->default(fn (): ?int => app(SupplierPortalContext::class)->company()->default_currency_id)
                        ->preload(),
                    TextInput::make('available_quantity')
                        ->label('Available Quantity')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Leave empty when availability is unknown'),
                    TextInput::make('lead_time_days')
                        ->label('Lead Time')
                        ->integer()
                        ->minValue(0)
                        ->suffix('days'),
                ])
                ->columns(2),
            Section::make('Last Updated')
                ->schema([
                    TextEntry::make('supplier_price_updated_at')
                        ->label('Price Updated')
                        ->dateTime()
                        ->placeholder('Never'),
                    TextEntry::make('quantity_updated_at')
                        ->label('Quantity Updated')
                        ->dateTime()
                        ->placeholder('Never'),
                ])
                ->columns(2),
        ];
    }
}
