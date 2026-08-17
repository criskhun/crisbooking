<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $alert->title }}</title></head>
<body style="margin:0;padding:0;background:#f4f5f1;color:#18231f;font-family:Arial,sans-serif">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 14px;background:#f4f5f1"><tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;overflow:hidden;border:1px solid #dfe5e1;border-radius:16px;background:#fff">
            <tr><td style="padding:22px 26px;color:#fff;background:#173c34"><strong style="font-size:20px">Davao Rent Zone</strong><div style="margin-top:5px;color:#c9ded5;font-size:12px">Booking notification</div></td></tr>
            <tr><td style="padding:28px 26px"><p style="margin:0 0 12px;color:#66736e;font-size:14px">Hello {{ $recipient->name }},</p><h1 style="margin:0 0 14px;font-size:24px">{{ $alert->title }}</h1><p style="margin:0;color:#4d5d57;font-size:15px;line-height:1.65">{{ $alert->body }}</p><p style="margin:24px 0"><a href="{{ $alert->url }}" style="display:inline-block;padding:12px 18px;border-radius:9px;color:#fff;background:#173c34;font-size:14px;font-weight:bold;text-decoration:none">Open in Davao Rent Zone</a></p><p style="margin:0;color:#84908c;font-size:12px;line-height:1.5">You received this email because this notification was not opened in Davao Rent Zone. It remains available in your notification center.</p></td></tr>
        </table>
    </td></tr></table>
</body>
</html>
