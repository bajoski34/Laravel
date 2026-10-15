<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Services\Chargebacks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Backs the chargeback components. Registered by Route::flutterwaveAdmin(), and
 * every action needs the `flutterwave.manage` ability: define it in your app,
 * or every request is refused.
 */
class ChargebackController extends Controller
{
    public function accept(Request $request, string $chargeback): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);

        return $this->respond(fn () => app('flutterwave')->chargebacks()->accept($chargeback, $validated['comment']), "Chargeback {$chargeback} accepted. The customer will be refunded.");
    }

    public function decline(Request $request, string $chargeback): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
            'proof' => ['required', 'file', 'mimes:'.implode(',', Chargebacks::PROOF_TYPES), 'max:'.(intdiv(Chargebacks::MAX_PROOF_BYTES, 1024) - 1)],
        ], [
            'proof.mimes' => 'The proof must be a JPEG, PNG or PDF file.',
            'proof.max' => 'The proof must be smaller than 8MB.',
        ]);

        return $this->respond(fn () => app('flutterwave')->chargebacks()->declineWithProof($chargeback, $validated['comment'], $request->file('proof')), "Chargeback {$chargeback} disputed. Flutterwave emails you as the dispute moves on.");
    }

    private function respond(callable $call, string $success): RedirectResponse
    {
        try {
            $call();
        } catch (FlutterwaveException|InvalidArgument $e) {
            return back()->withErrors(['flutterwave' => $e->getMessage()]);
        }

        return back()->with('flutterwave.status', $success);
    }
}
