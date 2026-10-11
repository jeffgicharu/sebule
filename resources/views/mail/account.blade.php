@php
    $serif = "Georgia, 'Times New Roman', serif";
@endphp
<x-mail.shell :subject="$subject" :preheader="$lines[0] ?? ''" :why="$why">
    <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:1.6px; text-transform:uppercase; color:#5d574e;">Your account</p>
    <h1 style="margin:10px 0 0; font-family:{{ $serif }}; font-size:28px; line-height:1.2; font-weight:400; color:#1f1d1a;">{{ $heading }}</h1>

    @foreach ($lines as $line)
        <p style="margin:16px 0 0; font-size:16px; line-height:1.55; color:#1f1d1a;">{{ $line }}</p>
    @endforeach

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 0;">
        <tr>
            <td style="background-color:#2f4a3a; border-radius:999px;">
                <a href="{{ $url }}" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#f4efe6; text-decoration:none;">{{ $action }}</a>
            </td>
        </tr>
    </table>
</x-mail.shell>
