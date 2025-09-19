<?php

namespace App\Http\Requests;

use App\Support\PublicCardUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicMenuSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('public_card_url')) {
            $this->merge([
                'public_card_url' => PublicCardUrl::format($this->input('public_card_url')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'public_card_url' => [
                'sometimes',
                'required',
                'string',
                'alpha_dash',
                'max:64',
                Rule::unique('companies', 'public_card_url')->ignore($this->user()->company_id),
            ],
            'only_sufficient_stock' => 'sometimes|boolean',
            'with_pictures' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'public_card_url.unique' => 'ALREADY_TAKEN, désolé mais cette URL est déjà prise',
        ];
    }
}
