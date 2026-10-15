<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Services\Fx;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * The optional admin page from Route::flutterwaveAdmin(): balances, chargebacks and the FX
 * converter. Publish the views (--tag=flutterwave-views) to change it.
 */
class AdminController extends Controller
{
    public const SECTIONS = [
        'overview' => 'Overview',
        'transactions' => 'Transactions',
        'refunds' => 'Refunds',
        'settlements' => 'Settlements',
        'beneficiaries' => 'Beneficiaries',
    ];

    public function __invoke(Request $request): Response
    {
        Gate::authorize('flutterwave.manage');

        $section = array_key_exists((string) $request->query('section'), self::SECTIONS) ? (string) $request->query('section') : 'overview';
        $flutterwave = app('flutterwave');
        $query = $request->query();

        // Only the section on screen is loaded, so each visit makes just the calls it needs.
        $data = match ($section) {
            'transactions' => ['transactions' => $flutterwave->transactions()->panel($query)],
            'refunds' => ['refunds' => $flutterwave->refunds()->panel($query)],
            'settlements' => ['settlements' => $flutterwave->settlements()->panel($query)],
            'beneficiaries' => ['beneficiaries' => $flutterwave->beneficiaries()->panel($query)],
            default => $this->overview($request),
        };

        // Balances and disputes shouldn't be kept by browsers or proxies.
        return response()->view('flutterwave::admin.index', ['section' => $section, 'sections' => self::SECTIONS, ...$data])
            ->header('Cache-Control', 'no-store, private');
    }

    private function overview(Request $request): array
    {
        $flutterwave = app('flutterwave');
        $status = $request->query('chargebacks', 'initiated');

        try {
            $balances = $flutterwave->balances()->all()['data'] ?? [];
            $balancesError = null;
        } catch (FlutterwaveException $e) {
            $balances = [];
            $balancesError = $e->getMessage();
        }

        return [
            'balances' => array_values(array_filter($balances, static fn (array $balance) => (float) ($balance['available_balance'] ?? 0) > 0 || (float) ($balance['ledger_balance'] ?? 0) > 0)),
            'balancesError' => $balancesError,
            'chargebackStatus' => $status === 'all' ? 'all' : 'initiated',
            'chargebacks' => $flutterwave->chargebacks()->panel($status === 'all' ? [] : ['status' => 'initiated']),
            'fx' => $flutterwave->fx()->panel($request->query(Fx::QUOTE_PARAM), $request->query(Fx::TRADE_PARAM)),
        ];
    }
}
