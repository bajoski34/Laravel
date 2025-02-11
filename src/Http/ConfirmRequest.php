<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class ConfirmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Allow all requests by default, adjust as needed.
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string', // status should be a number and >= 1
            'transaction_id' => 'required|string', // transaction_id should be a non-empty string
            'tx_ref' => 'required|string'
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'The payment status is required.',
            'status.string' => 'The payment status must be a valid string.',
            'transaction_id.required' => 'The transaction ID is required.',
            'transaction_id.string' => 'The transaction ID must be a valid string.',
            'tx_ref.required' => 'The transaction reference is required.',
            'tx_ref.string' => 'The transaction reference must be a valid string.',
        ];
    }

    /**
     * Configure the validation to throw exceptions when validation fails.
     *
     * @return void
     */
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if (!app()->isProduction()) {
            Log::error('Flutterwave Validation failed in ConfirmRequest:', $validator->errors()->toArray());
        }

        parent::failedValidation($validator); // Let Laravel handle the rest of the failed validation
    }
}
