<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Backs the beneficiaries components. Registered by Route::flutterwaveAdmin(); every
 * action needs the `flutterwave.manage` ability.
 */
class BeneficiaryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        $validated = $request->validate([
            'account_bank' => ['required', 'string', 'max:20'],
            'account_number' => ['required', 'string', 'alpha_num', 'max:20'],
            'beneficiary_name' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
        ], [], ['account_bank' => 'bank', 'beneficiary_name' => 'name']);

        $flutterwave = app('flutterwave');
        $name = trim((string) ($validated['beneficiary_name'] ?? ''));

        // Flutterwave needs the name; when it's left blank, use the name on the account.
        if ($name === '') {
            try {
                $name = (string) ($flutterwave->banks()->resolveAccount($validated['account_number'], $validated['account_bank'])['data']['account_name'] ?? '');
            } catch (FlutterwaveException $e) {
                return back()->withErrors(['account_number' => "Couldn't find the name on that account: {$e->getMessage()}"])->withInput();
            }
        }

        $context = ['account_number' => $validated['account_number'], 'bank_code' => $validated['account_bank']];

        try {
            $beneficiary = $flutterwave->beneficiaries()->create($validated['account_number'], $validated['account_bank'], $name, $validated['currency'] ?? null)['data'] ?? [];
        } catch (FlutterwaveException|InvalidArgument $e) {
            Audit::record('beneficiary.create', [...$context, 'error' => $e->getMessage()], false);

            return back()->withErrors(['flutterwave' => $e->getMessage()])->withInput();
        }

        Audit::record('beneficiary.create', [...$context, 'beneficiary_id' => $beneficiary['id'] ?? null]);

        return back()->with('flutterwave.status', "Saved {$name} as a beneficiary.");
    }

    public function destroy(string $beneficiary): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        try {
            app('flutterwave')->beneficiaries()->delete($beneficiary);
        } catch (FlutterwaveException $e) {
            Audit::record('beneficiary.delete', ['beneficiary_id' => $beneficiary, 'error' => $e->getMessage()], false);

            return back()->withErrors(['flutterwave' => $e->getMessage()]);
        }

        Audit::record('beneficiary.delete', ['beneficiary_id' => $beneficiary]);

        return back()->with('flutterwave.status', 'Beneficiary deleted.');
    }
}
