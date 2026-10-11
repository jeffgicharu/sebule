{{-- The frame every email from the store is sent in. Email programs ignore style sheets, so each element carries its own styles. --}}
@props(['subject', 'preheader' => '', 'why'])

@php
    $showroom = config('store.showroom');
    $serif = "Georgia, 'Times New Roman', serif";
    $sans = "-apple-system, 'Segoe UI', Helvetica, Arial, sans-serif";
    $soft = '#5d574e';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4efe6; color:#1f1d1a; font-family:{{ $sans }}; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4efe6;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                    <tr>
                        <td style="padding:0 8px 18px; font-family:{{ $serif }}; font-size:26px; color:#2f4a3a;">
                            <a href="{{ route('home') }}" style="color:#2f4a3a; text-decoration:none;">Sebule</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#fffdf9; border:1px solid #ddd5c6; padding:32px 28px;">
                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 8px 0; font-size:13px; line-height:1.55; color:{{ $soft }};">
                            Sebule, {{ $showroom['street'] }}, {{ $showroom['city'] }}.<br>
                            {{ $why }}
                            @unless (config('store.email.reply_to'))
                                <br>This message was sent from an address that does not take replies.
                            @endunless
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
