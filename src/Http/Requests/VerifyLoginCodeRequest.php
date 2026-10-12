<?php

namespace AdamDziuk\PhoneLogin\Http\Requests;

use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Foundation\Http\FormRequest;

class VerifyLoginCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.digits' => PhoneLogin::message('code_digits'),
        ];
    }
}
