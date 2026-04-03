<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $requestReference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
        <div style="padding:18px 24px;background:#111827;color:#ffffff;">
            <div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;opacity:.75;">Finance Request</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;">{{ $requestReference }}</div>
        </div>

        <div style="padding:24px;line-height:1.7;font-size:14px;">
            <p style="margin-top:0;">Dear {{ $agentName }},</p>

            @if(filled($bodyText))
                <div style="white-space:normal;">{!! nl2br(e($bodyText)) !!}</div>
            @else
                <p>No message body was provided.</p>
            @endif

            <p style="margin-bottom:0;margin-top:24px;">
                Regards,<br>
                <strong>{{ $senderName }}</strong><br>
                <span>{{ $senderEmail }}</span>
            </p>
        </div>
    </div>
</body>
</html>
