<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Services\Fx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Backs the FX converter components. Registered by Route::flutterwaveAdmin(), and
 * every action needs the `flutterwave.manage` ability.
 *
 * Both actions redirect back with the quote or trade id in the query string
 * (fx_quote, fx_trade), so reloading the page keeps showing it.
 */
class FxController extends Controller
{
    public function quote(Request $request): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        $validated = $request->validate([
            'base' => ['required', 'string', 'size:3', 'alpha'],
            'target' => ['required', 'string', 'size:3', 'alpha', 'different:base'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $quote = app('flutterwave')->fx()->quote($validated['base'], $validated['target'], $validated['quantity']);
        } catch (FlutterwaveException|InvalidArgument $e) {
            return back()->withErrors(['flutterwave' => $e->getMessage()])->withInput();
        }

        return $this->backWith([Fx::QUOTE_PARAM => $quote['data']['id'] ?? null, Fx::TRADE_PARAM => null]);
    }

    public function trade(Request $request): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        $validated = $request->validate([
            'quote_id' => ['required', 'string'],
            'narration' => ['required', 'string', 'max:255'],
        ]);

        try {
            $trade = app('flutterwave')->fx()->tradeIfReady($validated['quote_id'], $validated['narration']);
        } catch (FlutterwaveException|InvalidArgument $e) {
            return back()->withErrors(['flutterwave' => $e->getMessage()]);
        }

        return $this->backWith([Fx::QUOTE_PARAM => $validated['quote_id'], Fx::TRADE_PARAM => $trade['data']['id'] ?? null]);
    }

    /**
     * Redirect to the previous page with these query parameters set (or removed, when null).
     */
    private function backWith(array $params): RedirectResponse
    {
        $previous = url()->previous();
        $parts = parse_url($previous);
        parse_str($parts['query'] ?? '', $query);

        $query = array_filter([...$query, ...$params], static fn ($value) => $value !== null && $value !== '');
        $base = strtok($previous, '?') ?: $previous;

        return redirect()->to($query === [] ? $base : $base.'?'.http_build_query($query));
    }
}
