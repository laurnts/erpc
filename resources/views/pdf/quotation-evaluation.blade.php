@extends('pdf.layout')

@section('title', 'Quotation Evaluation - ' . $qe->qe_number)

@push('styles')
<style>
    .page {
        padding: 10mm 10mm;
    }

    .qe-header {
        margin-bottom: 15px;
    }

    .qe-title {
        font-size: 16pt;
        font-weight: bold;
        color: #1e40af;
        text-align: center;
        margin-bottom: 10px;
    }

    .qe-info-table {
        width: 100%;
        margin-bottom: 15px;
    }

    .qe-info-table td {
        padding: 3px 5px;
        font-size: 9pt;
    }

    .qe-info-table .label {
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

    .comparison-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        margin-bottom: 15px;
    }

    .comparison-table th {
        background-color: #1e40af;
        color: white;
        padding: 6px 4px;
        text-align: center;
        border: 1px solid #1e40af;
        font-size: 8pt;
    }

    .comparison-table th.text-left {
        text-align: left;
    }

    .comparison-table td {
        padding: 5px 4px;
        border: 1px solid #e5e7eb;
        vertical-align: top;
    }

    .comparison-table td.text-right {
        text-align: right;
    }

    .comparison-table td.text-center {
        text-align: center;
    }

    .comparison-table tbody tr:nth-child(even) {
        background-color: #f9fafb;
    }

    .comparison-table tfoot td {
        font-weight: bold;
        background-color: #f1f5f9;
    }

    .supplier-info-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        margin-bottom: 15px;
    }

    .supplier-info-table th {
        background-color: #64748b;
        color: white;
        padding: 5px;
        text-align: left;
        border: 1px solid #64748b;
    }

    .supplier-info-table td {
        padding: 5px;
        border: 1px solid #e5e7eb;
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
    <div class="qe-header">
        <div class="qe-title">QUOTATION EVALUATION</div>
    </div>

    {{-- QE Information --}}
    <table class="qe-info-table">
        <tr>
            <td class="label">QE Number:</td>
            <td>{{ $qe->qe_number }}</td>
            <td class="label">Date:</td>
            <td>{{ $qe->qe_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Request:</td>
            <td>{{ $qe->request->request_number ?? '-' }}</td>
            <td class="label">Description:</td>
            <td>{{ $qe->description ?? '-' }}</td>
        </tr>
    </table>

    {{-- Item Comparison --}}
    <div class="section-title">Item Comparison</div>
    @php
        $items = $qe->getItems();
        $suppliers = collect($qe->getSuppliers());
    @endphp

    @if(count($items) > 0 && $suppliers->count() > 0)
        <table class="comparison-table">
            <thead>
                <tr>
                    <th class="text-left" style="width: 25%;">Item</th>
                    <th style="width: 8%;">Qty</th>
                    @foreach($suppliers as $supplier)
                        <th>{{ $supplier['name'] ?? 'Unknown' }}<br><small>({{ $supplier['currency_code'] ?? 'USD' }})</small></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['description'] ?? 'Unknown' }}</td>
                        <td class="text-center">{{ $item['quantity'] ?? 0 }} {{ $item['unit'] ?? '' }}</td>
                        @foreach($suppliers as $supplier)
                            @php
                                $supplierId = (string) $supplier['id'];
                                $priceData = $item['prices'][$supplierId] ?? null;
                                $isBestPrice = $priceData['is_best_price'] ?? false;
                            @endphp
                            <td class="text-right">
                                @if($priceData)
                                    {{ number_format($priceData['unit_price'] ?? 0, 2) }}/{{ $item['unit'] ?? 'ea' }}<br>
                                    <small>Item Total: {{ number_format($priceData['line_subtotal'] ?? 0, 2) }}</small>
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right">Subtotal</td>
                    @foreach($suppliers as $supplier)
                        <td class="text-right">{{ number_format($supplier['subtotal'] ?? 0, 2) }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td colspan="2" class="text-right">Tax</td>
                    @foreach($suppliers as $supplier)
                        <td class="text-right">{{ number_format($supplier['tax_total'] ?? 0, 2) }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td colspan="2" class="text-right"><strong>Grand Total</strong></td>
                    @foreach($suppliers as $supplier)
                        <td class="text-right"><strong>{{ number_format($supplier['grand_total'] ?? 0, 2) }}</strong></td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Supplier Information --}}
    <div class="section-title">Supplier Information</div>
    @php
        // Get supplier quote IDs from snapshot data
        $snapshotSuppliers = collect($qe->getSuppliers());
        
        if ($snapshotSuppliers->isEmpty()) {
            $liveSuppliers = collect([]);
        } else {
            // Get supplier quote IDs from snapshot
            $quoteIds = $snapshotSuppliers->pluck('id')->filter()->toArray();
            
            // Load actual supplier quotes with supplier relationships
            $quotes = \App\Models\SupplierQuote::query()
                ->whereIn('id', $quoteIds)
                ->with(['supplier', 'currency'])
                ->get()
                ->keyBy('id');
            
            // Build suppliers array with live data
            $liveSuppliers = [];
            foreach ($snapshotSuppliers as $snapshotSupplier) {
                $quoteId = $snapshotSupplier['id'] ?? null;
                $quote = $quotes->get($quoteId);
                
                if ($quote && $quote->supplier) {
                    $liveSuppliers[] = [
                        'id' => $quote->getKey(),
                        'name' => $quote->supplier->name ?? 'Unknown',
                        'currency_code' => $quote->currency?->code ?? 'USD',
                        'delivery_type' => $quote->supplier->delivery_type ?? null,
                        'delivery_type_details' => $quote->supplier->delivery_type_details ?? null,
                        'is_taxable' => $quote->supplier->is_taxable ?? false,
                        'delivery_term' => $quote->supplier->delivery_term ?? null,
                        'payment_terms_days' => $quote->supplier->payment_terms_days ?? null,
                    ];
                } else {
                    // Fallback to snapshot data if quote not found
                    $liveSuppliers[] = $snapshotSupplier;
                }
            }
            $liveSuppliers = collect($liveSuppliers);
        }
    @endphp
    @if($liveSuppliers->count() > 0)
        <table class="supplier-info-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Attribute</th>
                    @foreach($liveSuppliers as $supplier)
                        <th>{{ $supplier['name'] ?? 'Unknown' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Delivery Type</strong></td>
                    @foreach($liveSuppliers as $supplier)
                        <td>
                            {{ $supplier['delivery_type'] ?? '-' }}
                            @if(!empty($supplier['delivery_type_details']))
                                <br><small>({{ $supplier['delivery_type_details'] }})</small>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td><strong>Taxable</strong></td>
                    @foreach($liveSuppliers as $supplier)
                        <td>{{ ($supplier['is_taxable'] ?? false) ? 'Yes' : 'No' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td><strong>Delivery Term</strong></td>
                    @foreach($liveSuppliers as $supplier)
                        <td>{{ $supplier['delivery_term'] ?? '-' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td><strong>Payment Terms</strong></td>
                    @foreach($liveSuppliers as $supplier)
                        <td>{{ isset($supplier['payment_terms_days']) ? 'Net ' . $supplier['payment_terms_days'] . ' days' : '-' }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Approval Section --}}
    <div class="section-title">Central Purchasing</div>
    <div class="approval-section">
        <table class="approval-table">
            <tr>
                <td>
                    <div class="approval-role">Prepared By</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $qe->preparedBy?->name ?? '________________' }}</div>
                </td>
                <td>
                    <div class="approval-role">Dept Head of Sales</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $qe->deptHeadSales?->name ?? $qe->dept_head_sales_name ?? '________________' }}</div>
                </td>
                <td>
                    <div class="approval-role">Deputy Director</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $qe->deputyDirector?->name ?? $qe->deputy_director_name ?? '________________' }}</div>
                </td>
                <td>
                    <div class="approval-role">Approved By</div>
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $qe->approvedBy?->name ?? $qe->approved_by_name ?? '________________' }}</div>
                </td>
            </tr>
        </table>
    </div>
@endsection

@section('footer')
    {{ $qe->qe_number }} | Generated on {{ now()->format('d M Y H:i') }}
@endsection
