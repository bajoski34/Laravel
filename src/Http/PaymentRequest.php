<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class PaymentRequest extends FormRequest
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
            'amount' => 'required|numeric|min:1', // Amount to be paid (must be a positive number)
            'currency' => 'required|string|size:3', // Currency (e.g., USD)
            'email' => 'required|email', // Payer's email
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
            'amount.required' => 'The payment amount is required.',
            'amount.numeric' => 'The payment amount must be a valid number.',
            'currency.required' => 'The currency is required.',
            'currency.size' => 'The currency must be a 3-letter ISO code.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Configure the validation to throw exceptions when validation fails.
     *
     * @return bool
     */
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if (!app()->IsProduction()) {
            Log::error('Flutterwave Validation failed in ConfirmRequest:', $validator->errors()->toArray());
        }
        parent::failedValidation($validator);
    }
}
