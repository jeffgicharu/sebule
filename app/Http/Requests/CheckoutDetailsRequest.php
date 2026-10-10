<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Models\County;
use App\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Who an order is for and how it reaches them. Checkout needs no account: a
 * name and a phone number are enough, and an address only for a delivery.
 */
class CheckoutDetailsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $delivery = Rule::requiredIf(fn () => $this->input('method') === DeliveryMethod::Delivery->value);

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (Phone::normalise((string) $value) === null) {
                    $fail('Enter a Kenyan mobile number, such as 0712 345 678.');
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'method' => ['required', Rule::enum(DeliveryMethod::class)],
            'county_id' => [$delivery, 'nullable', 'integer', Rule::exists('counties', 'id')->whereNotNull('delivery_zone_id')],
            'town' => [$delivery, 'nullable', 'string', 'max:120'],
            'estate' => [$delivery, 'nullable', 'string', 'max:160'],
            'house' => ['nullable', 'string', 'max:60'],
            'directions' => ['nullable', 'string', 'max:500'],
            'marketing' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Tell us who the order is for.',
            'name.min' => 'Tell us who the order is for.',
            'phone.required' => 'We need a phone number to reach you about the order.',
            'email.email' => 'That email address does not look right. Leave it empty if you would rather not give one.',
            'method.required' => 'Choose delivery or collection.',
            'county_id.required' => 'Choose the county we are delivering to.',
            'county_id.exists' => 'We do not deliver to that county yet.',
            'town.required' => 'Tell us the town or area.',
            'estate.required' => 'Tell us the estate, building or street.',
        ];
    }

    /**
     * The details in the form the order keeps them: the phone number in one
     * shape, and no address when the customer is collecting.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        $data = $this->validated();
        $delivering = $data['method'] === DeliveryMethod::Delivery->value;

        return [
            'name' => trim($data['name']),
            'phone' => Phone::normalise($data['phone']),
            'email' => $data['email'] ?? null,
            'method' => $data['method'],
            'county_id' => $delivering ? (int) $data['county_id'] : null,
            'town' => $delivering ? trim((string) $data['town']) : null,
            'estate' => $delivering ? trim((string) $data['estate']) : null,
            'house' => $delivering ? ($data['house'] ?? null) : null,
            'directions' => $delivering ? ($data['directions'] ?? null) : null,
            'marketing' => (bool) ($data['marketing'] ?? false),
        ];
    }

    public function county(): ?County
    {
        return County::query()->deliveredTo()->with('zone')->find($this->details()['county_id']);
    }
}
