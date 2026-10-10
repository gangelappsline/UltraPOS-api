@extends('emails.layouts.base')

@section('title', 'Welcome to '.config('app.name'))

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;color:#111827;">Welcome aboard, {{ $user->name }}! 🎉</h1>

    <p>Your business <strong>{{ $business->name }}</strong> has been created successfully and your
        {{ config('ultrapos.trial_days', 14) }}-day free trial has started.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;border:1px solid #e5e7eb;border-radius:6px;">
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;width:40%;">Business type</td>
            <td style="padding:12px 16px;">{{ $business->business_type->label() }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">Currency</td>
            <td style="padding:12px 16px;">{{ $business->currency }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;">Timezone</td>
            <td style="padding:12px 16px;">{{ $business->timezone }}</td>
        </tr>
    </table>

    <p>Next steps:</p>
    <ol style="padding-left:20px;margin:0 0 16px;">
        <li>Verify your email address.</li>
        <li>Create your product catalog (or import a CSV).</li>
        <li>Invite your sellers and open a cash register.</li>
        <li>Start selling!</li>
    </ol>

    <p>If you have any questions, our documentation is available at
        <code>/api/documentation</code>.</p>
@endsection
