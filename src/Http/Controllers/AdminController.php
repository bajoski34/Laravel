<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Services\Fx;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * The optional admin page from Route::flutterwaveAdmin(): balances, chargebacks and the FX
 * converter. Publish the views (--tag=flutterwave-views) to change it.
 */
class AdminController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('flutterwave.manage');

        $flutterwave = app('flutterwave');
        $status = $request->query('chargebacks', 'initiated');

        try {
            $balances = $flutterwave->balances()->all()['data'] ?? [];
            $balancesError = null;
        } catch (FlutterwaveException $e) {
            $balances = [];
            $balancesError = $e->getMessage();
        }

        return view('flutterwave::admin.index', [
            'balances' => array_values(array_filter($balances, static fn (array $balance) => (float) ($balance['available_balance'] ?? 0) > 0 || (float) ($balance['ledger_balance'] ?? 0) > 0)),
            'balancesError' => $balancesError,
            'chargebackStatus' => $status === 'all' ? 'all' : 'initiated',
            'chargebacks' => $flutterwave->chargebacks()->panel($status === 'all' ? [] : ['status' => 'initiated']),
            'fx' => $flutterwave->fx()->panel($request->query(Fx::QUOTE_PARAM), $request->query(Fx::TRADE_PARAM)),
        ]);
    }
}
