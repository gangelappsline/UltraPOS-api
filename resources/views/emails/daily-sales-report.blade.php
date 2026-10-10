@extends('emails.layouts.base')

@section('title', 'Daily sales report')

@section('content')
    <h1 style="margin:0 0 4px;font-size:22px;color:#111827;">Daily sales report</h1>
    <p style="color:#6b7280;margin:0 0 16px;">{{ $business->name }} · {{ $summary['date'] ?? now()->toDateString() }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;">
        <tr>
            <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:bold;">Sales count</td>
            <td style="padding:10px;border:1px solid #e5e7eb;">{{ $summary['sales_count'] ?? 0 }}</td>
        </tr>
        <tr>
            <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:bold;">Subtotal</td>
            <td style="padding:10px;border:1px solid #e5e7eb;">{{ number_format((float) ($summary['subtotal'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:bold;">Tax</td>
            <td style="padding:10px;border:1px solid #e5e7eb;">{{ number_format((float) ($summary['tax'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:bold;">Discounts</td>
            <td style="padding:10px;border:1px solid #e5e7eb;">{{ number_format((float) ($summary['discount'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:bold;">Total sold</td>
            <td style="padding:10px;border:1px solid #e5e7eb;font-weight:bold;">{{ number_format((float) ($summary['total'] ?? 0), 2) }} {{ $business->currency }}</td>
        </tr>
        <tr>
            <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:bold;">Cancelled sales</td>
            <td style="padding:10px;border:1px solid #e5e7eb;">{{ $summary['cancelled_count'] ?? 0 }}</td>
        </tr>
        <tr>
            <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:bold;">Outstanding</td>
            <td style="padding:10px;border:1px solid #e5e7eb;">{{ number_format((float) ($summary['outstanding'] ?? 0), 2) }}</td>
        </tr>
    </table>

    @if(!empty($summary['by_payment_method']))
        <h2 style="font-size:16px;margin:24px 0 8px;">By payment method</h2>
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;">
            @foreach($summary['by_payment_method'] as $row)
                <tr>
                    <td style="padding:8px;border:1px solid #e5e7eb;text-transform:capitalize;">{{ $row->method }}</td>
                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $row->count }} payment(s)</td>
                    <td align="right" style="padding:8px;border:1px solid #e5e7eb;">{{ number_format((float) $row->amount, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($summary['top_products']))
        <h2 style="font-size:16px;margin:24px 0 8px;">Top products</h2>
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;">
            @foreach($summary['top_products'] as $row)
                <tr>
                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $row->product_name }}</td>
                    <td align="right" style="padding:8px;border:1px solid #e5e7eb;">{{ number_format((float) $row->units, 2) }} units</td>
                    <td align="right" style="padding:8px;border:1px solid #e5e7eb;">{{ number_format((float) $row->revenue, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
