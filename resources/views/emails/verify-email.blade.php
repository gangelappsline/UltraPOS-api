@extends('emails.layouts.base')

@section('title', 'Verify your email')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;color:#111827;">Verify your email address</h1>

    <p>Hello {{ $user->name }},</p>

    <p>Please click the button below to verify your email address. This link expires in 60 minutes.</p>

    <p style="text-align:center;margin:24px 0;">
        <a href="{{ $verificationUrl }}"
           style="background-color:#2563eb;color:#ffffff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
            Verify Email Address
        </a>
    </p>

    <p style="color:#6b7280;font-size:12px;">If the button does not work, copy this URL into your browser:<br>
        <span style="word-break:break-all;">{{ $verificationUrl }}</span></p>

    <p style="color:#6b7280;font-size:12px;">If you did not create an account, no further action is required.</p>
@endsection
