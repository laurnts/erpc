<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierArticleResource\Schemas;

use App\Models\Currency;
use App\Services\Portal\SupplierPortalContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * The supplier-facing article page. The read-only sections mirror the staff
 * ViewArticle layout (minus the suppliers table and staff-only metadata such
 * as the creator); the only writable fields are the four offer fields. The
 * action-level whitelist in UpdateSupplierArticleOffer is the enforcement
 * against tampered payloads — this form's field set is only the first layer.
 */
final readonly class SupplierArticleForm
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Grid::make(3)
                ->schema([
                    Section::make('Article Details')
                        ->schema([
                            TextEntry::make('article.code')
                                ->label('Code')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('article.name')
                                ->label('Name'),
                            TextEntry::make('article.sku')
                                ->label('SKU')
                                ->placeholder('—'),
                            TextEntry::make('article.unitOfMeasure.label')
                                ->label('Unit of Measure')
                                ->placeholder('—'),
                            TextEntry::make('article.description')
                                ->label('Description')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpan(2),
                    Section::make('Status')
                        ->schema([
                            IconEntry::make('article.is_active')
                                ->label('Active')
                                ->boolean(),
                            TextEntry::make('supplier_price_updated_at')
                                ->label('Price Updated')
                                ->dateTime()
                                ->placeholder('Never'),
                            TextEntry::make('quantity_updated_at')
                                ->label('Quantity Updated')
                                ->dateTime()
                                ->placeholder('Never'),
                        ])
                        ->columnSpan(1),
                ])
                ->columnSpan('full'),
            Section::make('Images')
                ->schema([
                    SpatieMediaLibraryImageEntry::make('article.product_images')
                        ->label('')
                        ->collection('product_images')
                        ->conversion('thumb')
                        ->placeholder('No images uploaded.'),
                ])
                ->collapsible()
                ->columnSpan('full'),
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
                ->columns(2)
                ->columnSpan('full'),
        ];
    }
}
