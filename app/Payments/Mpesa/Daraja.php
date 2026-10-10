<?php

namespace App\Payments\Mpesa;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Safaricom's Daraja API, as far as the store uses it: asking a phone to pay
 * (an STK push) and asking what became of a prompt.
 */
class Daraja
{
    private const TOKEN = 'mpesa.token';

    public function isConfigured(): bool
    {
        return filled(config('services.mpesa.consumer_key'))
            && filled(config('services.mpesa.consumer_secret'))
            && filled(config('services.mpesa.passkey'))
            && filled(config('services.mpesa.shortcode'));
    }

    /**
     * Send a prompt to a phone. Returns Safaricom's answer, which holds the
     * CheckoutRequestID the prompt is known by from then on.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when Safaricom does not accept the request
     */
    public function prompt(string $phone, int $shillings, string $reference, string $callbackUrl): array
    {
        $response = $this->http()->post('/mpesa/stkpush/v1/processrequest', $this->signed() + [
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $shillings,
            'PartyA' => $phone,
            'PartyB' => config('services.mpesa.shortcode'),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $reference,
            'TransactionDesc' => 'Order '.$reference,
        ]);

        $answer = (array) $response->json();

        if (! $response->successful() || (string) ($answer['ResponseCode'] ?? '') !== '0' || blank($answer['CheckoutRequestID'] ?? null)) {
            throw new RuntimeException((string) ($answer['errorMessage'] ?? $answer['ResponseDescription'] ?? 'M-Pesa did not accept the request.'));
        }

        return $answer;
    }

    /**
     * What became of a prompt. Returns the result code and its description, or
     * null while the customer has still to answer it or Safaricom cannot say.
     *
     * @return array{code: string, description: string}|null
     */
    public function result(string $checkoutRequestId): ?array
    {
        $response = $this->http()->post('/mpesa/stkpushquery/v1/query', $this->signed() + [
            'CheckoutRequestID' => $checkoutRequestId,
        ]);

        $answer = (array) $response->json();

        // Until the prompt is answered, Safaricom replies with an error saying it is still being processed.
        if (! $response->successful() || ! array_key_exists('ResultCode', $answer)) {
            return null;
        }

        return ['code' => (string) $answer['ResultCode'], 'description' => (string) ($answer['ResultDesc'] ?? '')];
    }

    /** The fields that prove a request comes from the store's shortcode. */
    private function signed(): array
    {
        $shortcode = (string) config('services.mpesa.shortcode');
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        return [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.config('services.mpesa.passkey').$timestamp),
            'Timestamp' => $timestamp,
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->timeout(25);
    }

    /** An access token, asked for once and kept until shortly before it runs out. */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::baseUrl($this->baseUrl())
            ->withBasicAuth((string) config('services.mpesa.consumer_key'), (string) config('services.mpesa.consumer_secret'))
            ->acceptJson()
            ->timeout(25)
            ->get('/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        $token = (string) $response->json('access_token');

        if (! $response->successful() || $token === '') {
            throw new RuntimeException('M-Pesa did not give the store an access token.');
        }

        Cache::put(self::TOKEN, $token, max(60, (int) $response->json('expires_in', 3599) - 120));

        return $token;
    }

    private function baseUrl(): string
    {
        return config('services.mpesa.environment') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }
}
