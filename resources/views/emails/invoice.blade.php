@extends('emails.layouts.base')

@section('title', 'Invoice '.$invoiceNumber)

@section('content')
    <h1 style="margin:0 0 4px;font-size:22px;color:#111827;">Invoice {{ $invoiceNumber }}</h1>
    <p style="color:#6b7280;margin:0 0 16px;">Issued {{ now()->toDateString() }}</p>

    <p>Hello {{ $owner->name }},</p>

    <p>Thank you for your subscription. Below are the details of your invoice:</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;border:1px solid #e5e7eb;border-radius:6px;">
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;width:40%;">Plan</td>
            <td style="padding:12px 16px;">{{ $subscription->plan?->name }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">Billing cycle</td>
            <td style="padding:12px 16px;text-transform:capitalize;">{{ $subscription->plan?->billing_cycle?->value }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">Period</td>
            <td style="padding:12px 16px;">
                {{ $subscription->starts_at?->toDateString() }} → {{ $subscription->ends_at?->toDateString() }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">Amount</td>
            <td style="padding:12px 16px;font-size:16px;font-weight:bold;">
                {{ number_format((float) ($subscription->plan?->price ?? 0), 2) }} USD
            </td>
        </tr>
    </table>

    <p style="color:#6b7280;font-size:12px;">This is an automatically generated invoice for your records.</p>
@endsection
