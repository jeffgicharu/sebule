@php
    use App\Enums\DeliveryMethod;
    use App\Support\Money;
    use App\Support\Phone;

    $delivering = $order->delivery_method === DeliveryMethod::Delivery;
    $showroom = config('store.showroom');
    $pieces = $order->items->sum('quantity');

    // Email programs ignore style sheets, so every element carries its own styles.
    $serif = "Georgia, 'Times New Roman', serif";
    $sans = "-apple-system, 'Segoe UI', Helvetica, Arial, sans-serif";
    $ink = '#1f1d1a';
    $soft = '#5d574e';
    $rule = '#ddd5c6';
@endphp
<x-mail.shell :subject="$wording->subject" :preheader="$wording->lines[0] ?? ''"
    :why="'You are getting this because an order was placed at '.parse_url(config('app.url'), PHP_URL_HOST).' with this address. If that was not you, there is nothing you need to do.'">

                            <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:1.6px; text-transform:uppercase; color:{{ $soft }};">Order {{ $order->number }}</p>
                            <h1 style="margin:10px 0 0; font-family:{{ $serif }}; font-size:28px; line-height:1.2; font-weight:400; color:{{ $ink }};">{{ $wording->heading }}</h1>

                            @foreach ($wording->lines as $line)
                                <p style="margin:16px 0 0; font-size:16px; line-height:1.55; color:{{ $ink }};">{{ $line }}</p>
                            @endforeach

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 0;">
                                <tr>
                                    <td style="background-color:#2f4a3a; border-radius:999px;">
                                        <a href="{{ $wording->url }}" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#f4efe6; text-decoration:none;">{{ $wording->action }}</a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin:34px 0 0; padding-top:24px; border-top:1px solid {{ $rule }}; font-family:{{ $serif }}; font-size:20px; font-weight:400; color:{{ $ink }};">What you ordered</h2>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px; font-size:15px; line-height:1.45;">
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td style="padding:10px 12px 10px 0; border-bottom:1px solid {{ $rule }}; vertical-align:top;">
                                            {{ $item->product_name }}
                                            <br><span style="color:{{ $soft }}; font-size:14px;">{{ $item->variant_label ? $item->variant_label.', ' : '' }}{{ $item->quantity }} at {{ Money::format($item->unit_price) }}@if ($item->made_to_order). Made to order in about {{ $item->lead_time_weeks }} weeks @endif</span>
                                        </td>
                                        <td align="right" style="padding:10px 0; border-bottom:1px solid {{ $rule }}; vertical-align:top; white-space:nowrap;">{{ Money::format($item->line_total) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding:12px 12px 0 0; color:{{ $soft }};">{{ $pieces }} {{ Str::plural('piece', $pieces) }}</td>
                                    <td align="right" style="padding:12px 0 0; white-space:nowrap;">{{ Money::format($order->subtotal) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 12px 0 0; color:{{ $soft }};">{{ $delivering ? 'Delivery to '.$order->county : 'Collection from the showroom' }}</td>
                                    <td align="right" style="padding:6px 0 0; white-space:nowrap;">{{ $delivering ? Money::format($order->delivery_fee) : 'Free' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 12px 0 0; font-weight:600;">Total</td>
                                    <td align="right" style="padding:14px 0 0; font-family:{{ $serif }}; font-size:22px; white-space:nowrap;">{{ Money::format($order->total) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:4px 0 0; font-size:13px; color:{{ $soft }};">Includes {{ Money::format($order->vat_amount) }} VAT at {{ rtrim(rtrim(number_format($order->vat_rate / 100, 2), '0'), '.') }}%</td>
                                </tr>
                            </table>

                            <h2 style="margin:30px 0 0; padding-top:24px; border-top:1px solid {{ $rule }}; font-family:{{ $serif }}; font-size:20px; font-weight:400; color:{{ $ink }};">{{ $delivering ? 'Delivery' : 'Collection' }}</h2>
                            <p style="margin:10px 0 0; font-size:15px; line-height:1.55;">
                                {{ $order->customer_name }}, {{ Phone::display($order->phone) }}<br>
                                @if ($delivering)
                                    {{ $order->addressLine() }}
                                    @if ($order->directions)<br><span style="color:{{ $soft }};">{{ $order->directions }}</span>@endif
                                @else
                                    Sebule showroom, {{ $showroom['street'] }}, {{ $showroom['city'] }}<br>
                                    <span style="color:{{ $soft }};">{{ \App\Messages\OrderWording::hours() }}</span>
                                @endif
                            </p>
</x-mail.shell>
