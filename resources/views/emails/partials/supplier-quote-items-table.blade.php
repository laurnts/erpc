@php
    /** @var \App\Models\SupplierQuote $quote */
    $quote->loadMissing('items.requestItem');
    $organizedItems = \App\Models\SupplierQuoteItem::organizeHierarchically($quote->items);
@endphp

@if($organizedItems->isNotEmpty())
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #2563eb;">
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 50%;">Description</th>
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; width: 35%;">Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($organizedItems as $index => $entry)
                @php
                    /** @var \App\Models\SupplierQuoteItem $item */
                    $item = $entry['item'];
                    $isChild = $entry['is_child'];
                    $rowBg = $isChild ? '#f9fafb' : ($index % 2 === 0 ? '#ffffff' : '#f9fafb');
                    $textColor = $isChild ? '#6b7280' : '#1f2937';
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
                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: {{ $textColor }};">{{ $item->unit_label }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
