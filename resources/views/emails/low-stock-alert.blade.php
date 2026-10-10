@extends('emails.layouts.base')

@section('title', 'Low stock alert')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;color:#b91c1c;">⚠️ Low stock alert</h1>

    <p>Hello {{ $owner->name }},</p>

    <p>The following product has reached or dropped below its minimum stock level:</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;border:1px solid #e5e7eb;border-radius:6px;">
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;width:40%;">Product</td>
            <td style="padding:12px 16px;">{{ $product->name }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">SKU</td>
            <td style="padding:12px 16px;">{{ $product->sku }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">Current stock</td>
            <td style="padding:12px 16px;color:#b91c1c;font-weight:bold;">{{ number_format($currentStock, 2) }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">Minimum stock</td>
            <td style="padding:12px 16px;">{{ number_format($minStock, 2) }}</td>
        </tr>
    </table>

    <p>We recommend creating a purchase order for this product as soon as possible.</p>
@endsection
