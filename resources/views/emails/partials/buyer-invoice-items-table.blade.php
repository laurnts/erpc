@php
    /** @var \App\Models\BuyerInvoice $invoice */
    $items = $invoice->items->sortBy('sort_order')->values();
    $currency = $invoice->currency;
@endphp

@if($items->isNotEmpty())
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
            @foreach($items as $index => $item)
                @php
                    $rowBg = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                @endphp
                <tr style="background-color: {{ $rowBg }}; border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $index + 1 }}</td>
                    <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb; font-weight: bold;">{{ $item->description }}</td>
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item->quantity }}</td>
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item->unit_label }}</td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber((float) $item->unit_price_exc_tax) : number_format((float) $item->unit_price_exc_tax, 2) }}</td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber((float) $item->line_tax) : number_format((float) $item->line_tax, 2) }}</td>
                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; font-weight: bold;">{{ $currency ? $currency->formatNumber((float) $item->line_total) : number_format((float) $item->line_total, 2) }}</td>
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
                        <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937; width: 120px;">{{ $currency ? $currency->format((float) $invoice->subtotal) : number_format((float) $invoice->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">Tax:</td>
                        <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">{{ $currency ? $currency->format((float) $invoice->tax_total) : number_format((float) $invoice->tax_total, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 8px 0;">
                            <div style="height: 2px; background-color: #2563eb;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">Grand Total:</td>
                        <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">{{ $currency ? $currency->format((float) $invoice->total) : number_format((float) $invoice->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif
