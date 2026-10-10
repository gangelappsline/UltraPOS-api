<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background-color:#111827;padding:20px 32px;">
                        <span style="color:#ffffff;font-size:20px;font-weight:bold;">{{ config('app.name') }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;color:#1f2937;font-size:14px;line-height:1.6;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px;background-color:#f9fafb;color:#6b7280;font-size:12px;border-top:1px solid #e5e7eb;">
                        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                        You are receiving this email because an account is associated with your address.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
