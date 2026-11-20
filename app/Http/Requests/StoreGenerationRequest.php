<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prompt' => [
                'required',
                'string',
                'max:4000',
            ],

            'size' => [
                'required',
                'string',
                Rule::in([
                    '1024x1024',
                    '1536x1024',
                    '1024x1536',
                ]),
            ],

            'quality' => [
                'required',
                'string',
                Rule::in([
                    'low',
                    'medium',
                    'high',
                ]),
            ],

            'reference_images' => [
                'nullable',
                'array',
                'max:4',
            ],

            'reference_images.*' => [
                'required',
                'string',
                'url:http,https',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'Please enter an image prompt.',
            'prompt.max' => 'Prompt cannot exceed 4,000 characters.',
            'size.in' => 'The selected image dimensions are invalid.',
            'quality.in' => 'The selected image quality is invalid.',
            'reference_images.max' => 'You can add a maximum of 4 reference images.',
            'reference_images.*.url' => 'Each reference image must be a valid HTTP or HTTPS URL.',
        ];
    }
}
