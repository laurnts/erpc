@extends('pdf.layout')

@section('title', 'Profit & Loss - ' . $pnl->pnl_number)

@push('styles')
<style>
    .page {
        padding: 10mm 10mm;
    }

    .pnl-header {
        margin-bottom: 15px;
    }

    .pnl-title {
        font-size: 16pt;
        font-weight: bold;
        color: #1e40af;
        text-align: center;
        margin-bottom: 10px;
    }

    .pnl-info-table {
        width: 100%;
        margin-bottom: 15px;
    }

    .pnl-info-table td {
        padding: 3px 5px;
        font-size: 9pt;
    }

    .pnl-info-table .label {
        font-weight: bold;
        color: #4b5563;
        width: 120px;
    }

    .section-title {
        font-size: 11pt;
        font-weight: bold;
        color: #1e40af;
        border-bottom: 1px solid #1e40af;
        padding-bottom: 5px;
        margin-bottom: 10px;
        margin-top: 15px;
    }

    .supplier-header {
        background-color: #1e40af;
        color: white;
        padding: 8px 10px;
        font-size: 10pt;
        font-weight: bold;
        margin-top: 15px;
    }

    .supplier-header .supplier-totals {
        font-weight: normal;
        font-size: 8pt;
        float: right;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        margin-bottom: 10px;
    }

    .items-table th {
        background-color: #ffffff;
        color: #000000;
        padding: 5px 4px;
        text-align: left;
        border: 1px solid #e5e7eb;
        font-size: 8pt;
    }

    .items-table th.text-right {
        text-align: right;
    }

    .items-table th.text-center {
        text-align: center;
    }

    .items-table td {
        padding: 5px 4px;
        border: 1px solid #e5e7eb;
        vertical-align: top;
    }

    .items-table td.text-right {
        text-align: right;
    }

    .items-table td.text-center {
        text-align: center;
    }

    .items-table tbody tr:nth-child(even) {
        background-color: #f9fafb;
    }

    .items-table tbody tr.row-main {
        background-color: #eff6ff;
        font-weight: 600;
        border-left: 3px solid #3b82f6;
    }

    .items-table tbody tr.row-child {
        color: #9ca3af;
        font-size: 7.5pt;
    }

    .items-table tbody tr.row-child td {
        color: #9ca3af;
    }

    .items-table tbody tr.row-main td.line-total {
        font-weight: bold;
        color: #1d4ed8;
    }

    .items-table tfoot td {
        font-weight: bold;
        background-color: #dbeafe;
        border-top: 2px solid #3b82f6;
    }

    .items-table tfoot td.subtotal-amount {
        color: #1d4ed8;
        font-size: 9pt;
    }

    .positive {
        color: #059669;
    }

    .negative {
        color: #dc2626;
    }

    .approval-section {
        margin-top: 20px;
    }

    .approval-table {
        width: 100%;
        border-collapse: collapse;
    }

    .approval-table td {
        width: 25%;
        text-align: center;
        padding: 10px;
        vertical-align: top;
    }

    .approval-role {
        font-size: 8pt;
        color: #6b7280;
        margin-bottom: 30px;
    }

    .approval-name {
        font-size: 9pt;
        font-weight: bold;
        border-top: 1px solid #1f2937;
        padding-top: 5px;
    }

    .signature-line {
        height: 40px;
    }
</style>
@endpush

@section('content')
    {{-- Header --}}
    <div class="pnl-header">
        <div class="pnl-title">PROFIT & LOSS</div>
    </div>

    {{-- PNL Information --}}
    <table class="pnl-info-table">
        <tr>
            <td class="label">PNL Number:</td>
            <td>{{ $pnl->pnl_number }}</td>
            <td class="label">Date:</td>
            <td>{{ $pnl->pnl_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Request:</td>
            <td>{{ $pnl->request->request_number ?? '-' }}</td>
            <td class="label">Status:</td>
            <td>{{ $pnl->status->getLabel() }}</td>
        </tr>
        @if($pnl->description)
        <tr>
            <td class="label">Description:</td>
            <td colspan="3">{{ $pnl->description }}</td>
        </tr>
        @endif
    </table>

    {{-- Items by Supplier --}}
    <div class="section-title">Items by Supplier</div>
    
    @php
        // Use the buyer quote this PNL was created from (includes soft-deleted quotes)
        $buyerQuote = $pnl->resolveSourceBuyerQuote();
        $buyerQuote?->loadMissing('currency');
        
        $groupedItems = collect();
        $buyerCurrency = null;
        
        if ($buyerQuote !== null) {
            $buyerCurrency = $buyerQuote->currency;
            
            $items = $buyerQuote->items()
                ->whereNotNull('request_item_id')
                ->with(['supplierQuoteItem.supplierQuote.supplier', 'article', 'requestItem'])
                ->orderBy('sort_order')
                ->get();
            
            $groupedItems = $items->groupBy(function ($item) {
                return $item->supplierQuoteItem?->supplierQuote?->supplier_id ?? 0;
            });
        }
        
        $grandTotalCost = 0;
        $grandTotalSell = 0;
    @endphp

    @if($groupedItems->isNotEmpty())
        @foreach($groupedItems as $supplierId => $supplierItems)
            @php
                $firstItem = $supplierItems->first();
                $supplier = $firstItem->supplierQuoteItem?->supplierQuote?->supplier;
                $supplierName = $supplier?->name ?? 'No Supplier';
                $groupTotals = \App\Models\BuyerQuoteItem::collectTotals($supplierItems);
                $groupHasChildLines = $supplierItems->contains(fn ($item) => $item->isChildItem());
                $supplierCostTotal = $groupTotals->costTotal;
                $supplierNetSell = $groupTotals->subtotal;      // net revenue (margin base)
                $supplierMargin = $groupTotals->marginAmount;   // net sell - cost (VAT excluded)
                $supplierGrossTotal = $groupTotals->grandTotal; // gross, for the Line Total footer
                $organizedItems = \App\Models\BuyerQuoteItem::organizeHierarchically($supplierItems);

                $grandTotalCost += $supplierCostTotal;
                $grandTotalSell += $supplierNetSell;
            @endphp
            
            <div class="supplier-header">
                {{ $supplierName }}
                <span class="supplier-totals">
                    Cost: {{ number_format($supplierCostTotal, 2) }} | 
                    Sell: {{ number_format($supplierNetSell, 2) }} |
                    Margin: {{ number_format($supplierMargin, 2) }}
                </span>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Item</th>
                        <th class="text-center" style="width: 8%;">Qty</th>
                        <th class="text-right" style="width: 12%;">Cost</th>
                        <th class="text-right" style="width: 12%;">Sell</th>
                        <th class="text-right" style="width: 10%;">Tax</th>
                        <th class="text-right" style="width: 10%;">Margin %</th>
                        <th class="text-right" style="width: 13%;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($organizedItems as $entry)
                        @php
                            /** @var \App\Models\BuyerQuoteItem $item */
                            $item = $entry['item'];
                            $isChild = $entry['is_child'];
                            $lineTax = $item->getEffectiveLineTax();
                            $lineTotal = $item->getEffectiveLineTotal();
                            $marginPercent = $item->getDisplayMarginPercent();
                        @endphp
                        <tr class="{{ $isChild ? 'row-child' : 'row-main' }}">
                            <td>
                                @if($isChild)
                                    &nbsp;&nbsp;&nbsp;↳
                                @endif
                                @if($item->article)
                                    [{{ $item->article->code }}] {{ $item->article->name }}
                                @else
                                    {{ $item->description }}
                                @endif
                            </td>
                            <td class="text-center">{{ number_format((float) $item->quantity, 0) }} {{ $item->unit_label }}</td>
                            <td class="text-right">{{ number_format((float) $item->cost_price, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $item->unit_price_exc_tax, 2) }}</td>
                            <td class="text-right">{{ number_format($lineTax, 2) }}</td>
                            <td class="text-right {{ $marginPercent >= 0 ? 'positive' : 'negative' }}">{{ number_format($marginPercent, 0) }}%</td>
                            <td class="text-right line-total">{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right">
                            Supplier Subtotal
                            @if($groupHasChildLines)
                                <br><span style="font-size: 7pt; font-weight: normal;">(main items)</span>
                            @endif
                        </td>
                        <td class="text-right subtotal-amount">{{ number_format($supplierGrossTotal, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endforeach
        
        {{-- Grand Total --}}
        @php
            $grandTotalMargin = $grandTotalSell - $grandTotalCost;
            $grandTotalMarginPercent = $grandTotalSell > 0 ? ($grandTotalMargin / $grandTotalSell) * 100 : 0;
        @endphp
        <table class="items-table" style="margin-top: 15px;">
            <tr style="background-color: #1e40af; color: white;">
                <td style="font-weight: bold;">GRAND TOTAL</td>
                <td class="text-right">Cost: {{ number_format($grandTotalCost, 2) }}</td>
                <td class="text-right">Sell: {{ number_format($grandTotalSell, 2) }}</td>
                <td class="text-right {{ $grandTotalMargin >= 0 ? '' : 'negative' }}">
                    Margin: {{ number_format($grandTotalMargin, 2) }} ({{ number_format($grandTotalMarginPercent, 1) }}%)
                </td>
            </tr>
        </table>
    @else
        <p>No items available.</p>
    @endif

    {{-- Approval Section --}}
    <div class="section-title">Central Purchasing</div>
    <div class="approval-section">
        <table class="approval-table">
            <tr>
                <td>
                    <div class="approval-role">Prepared By</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $pnl->preparedBy?->name ?? '________________' }}</div>
                </td>
                <td>
                    <div class="approval-role">Dept Head of Sales</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $pnl->deptHeadSales?->name ?? $pnl->dept_head_sales_name ?? '________________' }}</div>
                </td>
                <td>
                    <div class="approval-role">Deputy Director</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $pnl->deputyDirector?->name ?? $pnl->deputy_director_name ?? '________________' }}</div>
                </td>
                <td>
                    <div class="approval-role">Approved By</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $pnl->approvedBy?->name ?? $pnl->approved_by_name ?? '________________' }}</div>
                </td>
            </tr>
        </table>
    </div>
@endsection

@section('footer')
    {{ $pnl->pnl_number }} | Generated on {{ now()->format('d M Y H:i') }}
@endsection
