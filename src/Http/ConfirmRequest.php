<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http;

use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string',
            'tx_ref' => 'required|string',
            // Absent when the customer closes the modal without paying.
            'transaction_id' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'The payment status is required.',
            'status.string' => 'The payment status must be a valid string.',
            'transaction_id.string' => 'The transaction ID must be a valid string.',
            'tx_ref.required' => 'The transaction reference is required.',
            'tx_ref.string' => 'The transaction reference must be a valid string.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::channel('flutterwave')->error('Flutterwave Validation failed in ConfirmRequest:', $validator->errors()->toArray());
        $errors = (new ValidationException($validator))->errors();

        if (! app()->isProduction()) {
            throw new InvalidArgument('Flutterwave Validation failed in ConfirmRequest: '.json_encode($errors));
        }

        throw new HttpResponseException(response()->json([
            'error' => $errors,
            'status_code' => JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }
}
