<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Route;

/**
 * Saved transfer recipients.
 *
 * @see https://developer.flutterwave.com/reference/create-a-beneficiary
 */
class Beneficiaries extends Service
{
    /**
     * Save a recipient. Get the bank code from banks()->options(). $name is required by
     * Flutterwave; resolve it first with banks()->resolveAccount() if you don't have it.
     */
    public function create(string $accountNumber, string $bankCode, ?string $name = null, ?string $currency = null, ?string $bankName = null): array
    {
        if ($name === null || trim($name) === '') {
            throw new InvalidArgument('A beneficiary needs the account holder\'s name. Look it up with banks()->resolveAccount() if you don\'t have it.');
        }

        return $this->client->post('beneficiaries', $this->filter([
            'account_number' => $accountNumber,
            'account_bank' => $bankCode,
            'beneficiary_name' => $name,
            'currency' => $currency ? strtoupper($currency) : null,
            'bank_name' => $bankName,
        ]));
    }

    public function all(array $filters = []): array
    {
        return $this->client->get('beneficiaries', $filters);
    }

    public function find(int|string $id): array
    {
        return $this->client->get("beneficiaries/{$id}");
    }

    public function delete(int|string $id): array
    {
        return $this->client->delete("beneficiaries/{$id}");
    }

    /**
     * Everything the beneficiaries components need. Pass the request's query: `page`, and
     * `country` for the bank list (default from config).
     */
    public function panel(array $query = []): array
    {
        $country = strtoupper((string) ($query['country'] ?? $this->config['country'] ?? 'NG'));
        $routes = Route::has('flutterwave.beneficiaries.store');
        $panel = ['items' => [], 'page' => null, 'error' => null, 'country' => $country, 'banks' => [], 'banks_error' => null, 'urls' => $routes ? ['create' => route('flutterwave.beneficiaries.store')] : null];

        try {
            $response = $this->all(array_filter(['page' => $query['page'] ?? null]));
            $panel['page'] = $response['meta']['page_info'] ?? null;
            $panel['items'] = array_map(static fn (array $beneficiary) => [
                'id' => $beneficiary['id'] ?? null,
                'full_name' => $beneficiary['full_name'] ?? null,
                'account_number' => $beneficiary['account_number'] ?? null,
                'bank_code' => $beneficiary['bank_code'] ?? null,
                'bank_name' => $beneficiary['bank_name'] ?? null,
                'created_at' => $beneficiary['created_at'] ?? null,
                'urls' => $routes ? ['delete' => route('flutterwave.beneficiaries.destroy', $beneficiary['id'])] : null,
            ], $response['data'] ?? []);
        } catch (FlutterwaveException $e) {
            $panel['error'] = $e->getMessage();
        }

        if ($routes) {
            try {
                $panel['banks'] = app('flutterwave')->banks()->options($country);
            } catch (FlutterwaveException $e) {
                $panel['banks_error'] = $e->getMessage();
            }
        }

        return $panel;
    }
}
