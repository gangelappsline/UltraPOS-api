@extends('emails.layouts.base')

@section('title', 'Reset your password')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;color:#111827;">Reset your password</h1>

    <p>Hello {{ $user->name }},</p>

    <p>We received a request to reset your password. Use the token below within the next 60 minutes:</p>

    <p style="text-align:center;margin:24px 0;">
        <span style="background-color:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:12px 20px;font-family:monospace;font-size:16px;letter-spacing:1px;">
            {{ $token }}
        </span>
    </p>

    <p style="text-align:center;">
        <a href="{{ $resetUrl }}"
           style="background-color:#2563eb;color:#ffffff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
            Open password reset page
        </a>
    </p>

    <p style="color:#6b7280;font-size:12px;">API clients can POST the token to
        <code>/api/v1/auth/reset-password</code>. If you did not request this, you can safely ignore this email —
        your password will not change.</p>
@endsection
