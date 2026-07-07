@php
    /** @var \App\Models\BuyerOrder $order */
    $order->loadMissing(['items.requestItem', 'buyerQuote.currency']);
    $organizedItems = \App\Models\BuyerOrderItem::organizeHierarchically($order->items);
    $currency = $order->buyerQuote?->currency;
@endphp

@if($organizedItems->isNotEmpty())
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #2563eb;">
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 40%;">Description</th>
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 8%;">Unit</th>
                <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 12%;">Unit Price</th>
                <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 12%;">Tax</th>
                <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; width: 13%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($organizedItems as $index => $entry)
                @php
                    /** @var \App\Models\BuyerOrderItem $item */
                    $item = $entry['item'];
                    $isChild = $entry['is_child'];
                    $rowBg = $isChild ? '#f9fafb' : ($index % 2 === 0 ? '#ffffff' : '#f9fafb');
                    $textColor = $isChild ? '#6b7280' : '#1f2937';
                    $lineTax = (float) $item->tax_amount * (float) $item->quantity;
                @endphp
                <tr style="background-color: {{ $rowBg }}; border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $index + 1 }}</td>
                    <td style="padding: 10px 8px; text-align: left; font-size: {{ $isChild ? '12px' : '13px' }}; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;{{ $isChild ? ' padding-left: 24px;' : ' font-weight: bold;' }}">
                        @if($isChild)
                            <span style="color: #9ca3af; margin-right: 4px;">↳</span>
                        @endif
                        {{ $item->description }}
                        @if($item->notes)
                            <br><small style="color: #6b7280; font-size: 11px;">{{ $item->notes }}</small>
                        @endif
                    </td>
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $item->quantity }}</td>
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $item->unit_label }}</td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber((float) $item->unit_price_exc_tax) : number_format((float) $item->unit_price_exc_tax, 2) }}</td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber($lineTax) : number_format($lineTax, 2) }}</td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: {{ $textColor }}; font-weight: {{ $isChild ? 'normal' : 'bold' }};">{{ $currency ? $currency->formatNumber((float) $item->line_total) : number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
        <tr>
            <td align="right">
                <table cellpadding="0" cellspacing="0" style="margin-left: auto; width: 300px;">
                    <tr>
                        <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">Subtotal:</td>
                        <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937; width: 120px;">{{ $currency ? $currency->format((float) $order->subtotal) : number_format((float) $order->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">Tax:</td>
                        <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">{{ $currency ? $currency->format((float) $order->tax_total) : number_format((float) $order->tax_total, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 8px 0;">
                            <div style="height: 2px; background-color: #2563eb;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">Grand Total:</td>
                        <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">{{ $currency ? $currency->format((float) $order->total) : number_format((float) $order->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif
