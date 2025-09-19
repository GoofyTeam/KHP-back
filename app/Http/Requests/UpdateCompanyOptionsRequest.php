<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCompanyOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->exists('public_menu_card_url')) {
            $publicUrl = $this->input('public_menu_card_url');
            $data['public_menu_card_url'] = $this->formatPublicMenuCardUrl($publicUrl);
        }

        if ($this->has('only_sufficient_stock')) {
            $data['show_out_of_stock_menus_on_card'] = ! $this->boolean('only_sufficient_stock');
        }

        if ($this->has('with_pictures')) {
            $data['show_menu_images'] = $this->boolean('with_pictures');
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'auto_complete_menu_orders' => 'sometimes|boolean',
            'open_food_facts_language' => 'sometimes|in:fr,en',
            'public_menu_card_url' => [
                'sometimes',
                'required',
                'string',
                'alpha_dash',
                'max:255',
                Rule::unique('companies', 'public_menu_card_url')->ignore($companyId),
            ],
            'show_out_of_stock_menus_on_card' => 'sometimes|boolean',
            'show_menu_images' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'public_menu_card_url.unique' => 'ALREADY_TAKEN, désolé mais cette URL est déjà prise',
        ];
    }

    private function formatPublicMenuCardUrl(?string $value): string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return $trimmed;
        }

        $slug = Str::slug($trimmed);

        return $slug === '' ? $trimmed : $slug;
    }
}
