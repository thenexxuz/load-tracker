<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AppSettingsUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'google_sheet_url' => ['nullable', 'url', 'max:2048'],
            'app_name' => ['nullable', 'string', 'max:255'],
            'app_logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_sheet_url.url' => 'Enter a valid Google Sheets URL.',
            'app_logo.image' => 'The app logo must be an image file.',
            'app_logo.max' => 'The app logo must be at most 2MB.',
        ];
    }
}
