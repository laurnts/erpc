@php
    $invoice->loadMissing(['items.unitOfMeasure', 'currency', 'buyerOrder.buyer', 'request']);
    $buyer = $invoice->buyerOrder?->buyer ?? $invoice->request?->buyer;
    $buyerOrder = $invoice->buyerOrder;
    $currency = $invoice->currency;
@endphp
@include('emails.partials.email-shell-top', ['emailTitle' => 'Invoice '.$invoice->invoice_number, 'team' => $team])
                                    <td width="60%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">{{ $invoice->invoice_number }}</div>
                                        <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                            Invoice Date: {{ $invoice->issued_at?->format('d M Y') ?? now()->format('d M Y') }}<br>
                                            @if($invoice->due_at)
                                                Due Date: {{ $invoice->due_at->format('d M Y') }}<br>
                                            @endif
                                            @if($buyerOrder)
                                                Order Reference: <strong style="color: #1f2937;">{{ $buyerOrder->order_number }}</strong><br>
                                            @endif
                                            @if($invoice->request)
                                                Request Reference: <strong style="color: #1f2937;">{{ $invoice->request->request_number }}</strong>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
@include('emails.partials.email-separator')
@include('emails.partials.email-bill-to', ['company' => $buyer, 'fallbackName' => 'Buyer'])
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            @if(!empty($content) && trim($content) !== '')
                                <div style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    {!! nl2br(e($content)) !!}
                                </div>
                            @else
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Dear {{ $buyer->name ?? 'Buyer' }},
                                </p>

                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Please find below the details of your invoice.
                                </p>
                            @endif

                            @if($invoice->items && $invoice->items->count() > 0)
                                @include('emails.partials.buyer-invoice-items-table', ['invoice' => $invoice])
                            @endif

                            @if($invoice->net_days)
                                <p style="font-size: 13px; line-height: 1.6; color: #6b7280; margin-top: 25px;">
                                    <strong>Payment Terms:</strong>
                                    Net {{ $invoice->net_days }} days from invoice date
                                </p>
                            @endif

                            @if($invoice->notes)
                                <p style="font-size: 13px; line-height: 1.6; color: #1f2937; margin-top: 15px;">
                                    {{ $invoice->notes }}
                                </p>
                            @endif

@include('emails.partials.email-signature', ['team' => $team])
                        </td>
                    </tr>
@include('emails.partials.email-shell-bottom')
