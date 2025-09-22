<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('image_url')) {
            $this->merge([
                'image_url' => trim((string) $this->input('image_url')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'image' => ['required_without:image_url', 'nullable', 'image', 'max:2048', 'mimes:jpeg,jpg,png,gif,webp,bmp,svg,heic'],
            'image_url' => ['required_without:image', 'nullable', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required_without' => "Veuillez fournir un fichier image ou une URL d'image.",
            'image_url.required_without' => "Veuillez fournir un fichier image ou une URL d'image.",
        ];
    }
}
