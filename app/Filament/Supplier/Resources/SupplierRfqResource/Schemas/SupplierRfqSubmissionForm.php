<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierRfqResource\Schemas;

use App\Models\Currency;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * Portal quote-submission form. Deliberately narrow: per-main-item unit
 * prices, currency, validity, notes, and the quotation document. There is
 * NO exchange-rate input — the rate is always resolved server-side (it
 * drives base-currency comparison ranking). Child/detail rows of service
 * quotes are read-only and never priced here.
 */
final readonly class SupplierRfqSubmissionForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(SupplierQuote $quote): array
    {
        $priceInputs = $quote->items
            ->filter(fn (SupplierQuoteItem $item): bool => $item->requestItem === null
                || $item->requestItem->parent_id === null)
            ->map(fn (SupplierQuoteItem $item): TextInput => TextInput::make('item_prices.'.$item->getKey())
                ->label($item->description)
                ->helperText(sprintf(
                    'Quantity: %s %s',
                    number_format((float) $item->quantity, 2),
                    $item->unit_label,
                ))
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.0001))
            ->values()
            ->all();

        return [
            Section::make('Your Prices')
                ->description('Unit prices for each requested item.')
                ->schema($priceInputs),
            Section::make('Quote Details')
                ->schema([
                    Select::make('currency_id')
                        ->label('Currency')
                        ->options(
                            Currency::query()
                                ->where('is_active', true)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (Currency $currency): array => [
                                    $currency->getKey() => "{$currency->code} - {$currency->name}",
                                ])
                        )
                        ->default($quote->currency_id)
                        ->required()
                        ->selectablePlaceholder(false),
                    DatePicker::make('valid_until')
                        ->label('Quote Valid Until')
                        ->minDate(today()->addDay())
                        ->default($quote->valid_until),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3),
                    FileUpload::make('quotation_file')
                        ->label('Quotation Document')
                        ->helperText('Optionally attach your quotation document (PDF, Excel, Word, Images).')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])
                        ->disk('local')
                        ->directory('supplier-quotes/quotation')
                        ->visibility('private')
                        ->maxSize(10240),
                ]),
        ];
    }
}
