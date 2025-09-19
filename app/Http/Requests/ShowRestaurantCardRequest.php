<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowRestaurantCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'public_menu_card_url' => $this->route('public_menu_card_url'),
        ]);
    }

    public function rules(): array
    {
        return [
            'public_menu_card_url' => 'required|string|alpha_dash|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'public_menu_card_url.alpha_dash' => "Le format de l'URL publique est invalide.",
            'public_menu_card_url.max' => "Le format de l'URL publique est invalide.",
        ];
    }
}
