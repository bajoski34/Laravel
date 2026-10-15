<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components;

use Flutterwave\Payments\Services\Fx;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;

/**
 * <x-flutterwave-fx :panel="Flutterwave::fx()->panel(request('fx_quote'), request('fx_trade'))" />
 *
 * Request a rate, trade it, and follow the trade. The forms post to the routes from
 * Route::flutterwaveAdmin(). While a quote or trade is pending, the page refreshes itself.
 */
class FxConverter extends Component
{
    public string $componentId;

    public function __construct(public array $panel, public int $refreshSeconds = 3)
    {
        $this->componentId = 'flw-fx-'.Str::lower(Str::random(10));
    }

    public function startOverUrl(): string
    {
        return request()->fullUrlWithoutQuery([Fx::QUOTE_PARAM, Fx::TRADE_PARAM]);
    }

    public static function money(?string $currency, mixed $amount): string
    {
        return is_numeric($amount) ? trim(($currency ?? '').' '.number_format((float) $amount, 2)) : '';
    }

    public function waiting(): bool
    {
        $trade = $this->panel['trade'] ?? null;
        $quote = $this->panel['quote'] ?? null;

        return $trade ? (bool) $trade['pending'] : (bool) ($quote['pending'] ?? false);
    }

    /**
     * The first validation or Flutterwave error from the last action. Read from the session
     * so the component also renders outside the web middleware group, where $errors isn't shared.
     */
    public function firstError(array $keys): ?string
    {
        $errors = session()->get('errors');

        if (! $errors instanceof ViewErrorBag) {
            return null;
        }

        foreach ($keys as $key) {
            if ($errors->has($key)) {
                return $errors->first($key);
            }
        }

        return null;
    }

    public function render()
    {
        return view('flutterwave::components.fx-converter');
    }
}
