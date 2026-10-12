<?php

namespace AdamDziuk\PhoneLogin\Http\Requests;

use AdamDziuk\PhoneLogin\PhoneLogin;
use AdamDziuk\PhoneLogin\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SendLoginCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $input = $this->input('phone');

        $this->merge([
            'phone' => is_string($input) ? app(PhoneNumber::class)->normalise($input) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...PhoneLogin::hooks()->sendRules,
            // Last, so no extra rule can take the allow-list's place.
            'phone' => ['required', 'string', 'max:20', $this->allowedCountry(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => PhoneLogin::message('phone_invalid'),
            ...PhoneLogin::hooks()->sendMessages,
        ];
    }

    /**
     * @param  Closure(string): mixed  $fail
     */
    private function allowedCountry(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! app(PhoneNumber::class)->isAllowed($value)) {
            $fail(PhoneLogin::message('phone_not_allowed'));
        }
    }
}
