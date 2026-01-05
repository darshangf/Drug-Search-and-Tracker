<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DrugSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'drug_name' => 'required|string|min:2|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'drug_name.required' => 'Drug name is required',
            'drug_name.string' => 'Drug name must be a valid string',
            'drug_name.min' => 'Drug name must be at least 2 characters',
            'drug_name.max' => 'Drug name must not exceed 255 characters',
        ];
    }
}
