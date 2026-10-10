@extends('emails.layouts.base')

@section('title', 'Receipt '.$sale->reference_number)

@section('content')
    <h1 style="margin:0 0 4px;font-size:22px;color:#111827;">Sales receipt</h1>
    <p style="color:#6b7280;margin:0 0 16px;">
        {{ $receipt['business']['name'] ?? '' }}
        @if(!empty($receipt['business']['tax_id'])) · Tax ID {{ $receipt['business']['tax_id'] }} @endif
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:16px;font-size:13px;">
        <tr>
            <td style="padding:4px 0;"><strong>Receipt:</strong> {{ $receipt['sale']['reference_number'] }}</td>
            <td style="padding:4px 0;text-align:right;"><strong>Date:</strong> {{ $receipt['sale']['completed_at'] }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;"><strong>Status:</strong> {{ strtoupper($receipt['sale']['status']) }} / {{ strtoupper($receipt['sale']['payment_status']) }}</td>
            <td style="padding:4px 0;text-align:right;"><strong>Served by:</strong> {{ $receipt['served_by'] ?? '—' }}</td>
        </tr>
        @if(!empty($receipt['customer']))
        <tr>
            <td colspan="2" style="padding:4px 0;"><strong>Customer:</strong> {{ $receipt['customer']['name'] }}</td>
        </tr>
        @endif
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
        <tr style="background-color:#f3f4f6;">
            <th align="left" style="padding:8px;border-bottom:1px solid #e5e7eb;">Item</th>
            <th align="right" style="padding:8px;border-bottom:1px solid #e5e7eb;">Qty</th>
            <th align="right" style="padding:8px;border-bottom:1px solid #e5e7eb;">Price</th>
            <th align="right" style="padding:8px;border-bottom:1px solid #e5e7eb;">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach($receipt['items'] as $item)
            <tr>
                <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ $item['name'] }}</td>
                <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ number_format($item['quantity'], 2) }}</td>
                <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ number_format($item['unit_price'], 2) }}</td>
                <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ number_format($item['total'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin-top:16px;font-size:13px;">
        <tr><td align="right" style="padding:2px 0;">Subtotal: <strong>{{ number_format($receipt['sale']['subtotal'], 2) }} {{ $receipt['business']['currency'] ?? '' }}</strong></td></tr>
        <tr><td align="right" style="padding:2px 0;">Discount: <strong>-{{ number_format($receipt['sale']['discount'], 2) }}</strong></td></tr>
        <tr><td align="right" style="padding:2px 0;">Tax: <strong>{{ number_format($receipt['sale']['tax'], 2) }}</strong></td></tr>
        <tr><td align="right" style="padding:8px 0;font-size:16px;border-top:2px solid #111827;">TOTAL: <strong>{{ number_format($receipt['sale']['total'], 2) }} {{ $receipt['business']['currency'] ?? '' }}</strong></td></tr>
        <tr><td align="right" style="padding:2px 0;">Paid: <strong>{{ number_format($receipt['sale']['paid_amount'], 2) }}</strong></td></tr>
        @if($receipt['sale']['change_amount'] > 0)
            <tr><td align="right" style="padding:2px 0;">Change: <strong>{{ number_format($receipt['sale']['change_amount'], 2) }}</strong></td></tr>
        @endif
        @if($receipt['sale']['balance'] > 0)
            <tr><td align="right" style="padding:2px 0;color:#dc2626;">Balance due: <strong>{{ number_format($receipt['sale']['balance'], 2) }}</strong></td></tr>
        @endif
    </table>

    @if(!empty($receipt['payments']))
        <p style="margin-top:16px;color:#6b7280;font-size:12px;">
            Payment methods:
            @foreach($receipt['payments'] as $payment)
                {{ strtoupper($payment['method']) }} {{ number_format($payment['amount'], 2) }}@if(!$loop->last), @endif
            @endforeach
        </p>
    @endif

    <p style="margin-top:24px;color:#6b7280;font-size:12px;">Thank you for your purchase!</p>
@endsection
