<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:5',
                'max:100',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->input(
                'start_date',
                now()->subDays(29)->toDateString()
            ),

            'end_date' => $this->input(
                'end_date',
                now()->toDateString()
            ),

            'per_page' => $this->input('per_page', 10),
        ]);
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' =>
                'End date must be after or equal to start date.',
        ];
    }
}
