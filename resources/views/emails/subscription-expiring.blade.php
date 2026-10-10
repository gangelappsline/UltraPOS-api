@extends('emails.layouts.base')

@section('title', 'Subscription expiring')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;color:#b45309;">Your subscription is expiring soon</h1>

    <p>Hello {{ $owner->name }},</p>

    <p>The subscription of your business to the
        <strong>{{ $subscription->plan?->name ?? 'current' }}</strong> plan
        @if($subscription->ends_at)
            will end on <strong>{{ $subscription->ends_at->toDateString() }}</strong>.
        @else
            is about to expire.
        @endif
    </p>

    <p>To avoid any interruption in your POS operations, renew your plan before the due date.</p>

    <p style="text-align:center;margin:24px 0;">
        <a href="{{ rtrim(config('app.url'), '/').'/api/v1/plans' }}"
           style="background-color:#2563eb;color:#ffffff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
            View available plans
        </a>
    </p>
@endsection
