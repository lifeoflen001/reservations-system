<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;background:#f4f6f8;color:#26364f;font-family:Arial,Helvetica,sans-serif;line-height:1.6;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader ?? $subject }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dfe5ec;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#ef7d22;padding:22px 28px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.02em;">
                            LODGIX <span style="display:block;margin-top:2px;font-size:10px;font-weight:600;letter-spacing:.12em;opacity:.9;">HOTEL MANAGEMENT SYSTEM</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px 28px;">
                            @if (! empty($title))
                                <h1 style="margin:0 0 18px;color:#1f2f49;font-size:24px;line-height:1.25;">{{ $title }}</h1>
                            @endif
                            <div style="color:#53657e;font-size:15px;white-space:normal;">{!! nl2br(e($body)) !!}</div>
                            @if (! empty($actionUrl))
                                <p style="margin:26px 0 4px;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#ef7d22;border-radius:5px;color:#ffffff;font-size:14px;font-weight:700;padding:12px 20px;text-decoration:none;">Open Lodgix</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e7ebf0;padding:18px 28px;color:#8a98aa;font-size:12px;">
                            This message was sent by {{ $propertyName ?? 'Lodgix' }}. If you did not expect it, you can safely ignore it.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
