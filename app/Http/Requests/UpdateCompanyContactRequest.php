<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach ([
            'contact_name',
            'contact_phone',
            'address_line',
            'postal_code',
            'city',
            'country',
        ] as $field) {
            if ($this->exists($field)) {
                $data[$field] = $this->normalizeNullableString($this->input($field));
            }
        }

        if ($this->exists('contact_email')) {
            $email = trim((string) $this->input('contact_email'));
            $data['contact_email'] = $email === '' ? null : mb_strtolower($email);
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'contact_name' => 'sometimes|nullable|string|max:255',
            'contact_email' => 'sometimes|nullable|email|max:255',
            'contact_phone' => 'sometimes|nullable|string|max:64',
            'address_line' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:32',
            'city' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
