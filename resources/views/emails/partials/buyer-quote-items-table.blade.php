@php
    /** @var \App\Models\BuyerQuote $quote */
    $quote->loadMissing('items.requestItem', 'currency');
    $organizedItems = \App\Models\BuyerQuoteItem::organizeHierarchically($quote->items);
@endphp

@if($organizedItems->isNotEmpty())
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse; border: 1px solid #e5e7eb;">
        <thead>
            <tr style="background-color: #2563eb;">
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 45%;">Description</th>
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 8%;">Unit</th>
                <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 15%;">Unit Price</th>
                <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; width: 17%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($organizedItems as $index => $entry)
                @php
                    /** @var \App\Models\BuyerQuoteItem $item */
                    $item = $entry['item'];
                    $isChild = $entry['is_child'];
                @endphp
                <tr style="border-bottom: 1px solid #e5e7eb;{{ $isChild ? ' background-color: #fafafa;' : '' }}">
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $index + 1 }}</td>
                    <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: {{ $isChild ? '#6b7280' : '#1f2937' }}; border-right: 1px solid #e5e7eb;{{ $isChild ? ' padding-left: 24px;' : ' font-weight: bold;' }}">
                        @if($isChild)
                            <span style="color: #9ca3af; margin-right: 4px;">↳</span>
                        @endif
                        {{ $item->description }}
                        @if($item->notes)
                            <br><small style="color: #6b7280; font-size: 11px;">{{ $item->notes }}</small>
                        @endif
                    </td>
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                        {{ $quote->currency ? $quote->currency->formatNumber((float) $item->quantity) : number_format((float) $item->quantity, 2) }}
                    </td>
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                        {{ $item->unit_label }}
                    </td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                        {{ $quote->currency ? $quote->currency->formatNumber((float) $item->unit_price_exc_tax) : number_format((float) $item->unit_price_exc_tax, 2) }}
                    </td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; font-weight: bold;">
                        {{ $quote->currency ? $quote->currency->formatNumber((float) $item->line_total) : number_format((float) $item->line_total, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f9fafb; border-top: 2px solid #2563eb;">
                <td colspan="4" style="padding: 12px 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                    Subtotal:
                </td>
                <td colspan="2" style="padding: 12px 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                    {{ $quote->currency ? $quote->currency->formatNumber((float) $quote->subtotal) : number_format((float) $quote->subtotal, 2) }}
                </td>
            </tr>
            @if((float) $quote->tax_total > 0)
                <tr style="background-color: #f9fafb;">
                    <td colspan="4" style="padding: 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                        Tax:
                    </td>
                    <td colspan="2" style="padding: 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                        {{ $quote->currency ? $quote->currency->formatNumber((float) $quote->tax_total) : number_format((float) $quote->tax_total, 2) }}
                    </td>
                </tr>
            @endif
            <tr style="background-color: #eff6ff; border-top: 2px solid #2563eb;">
                <td colspan="4" style="padding: 15px 8px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">
                    Grand Total:
                </td>
                <td colspan="2" style="padding: 15px 8px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">
                    {{ $quote->currency ? $quote->currency->formatNumber((float) $quote->total) : number_format((float) $quote->total, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>
@endif
