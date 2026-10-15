<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components\Concerns;

use Illuminate\Support\ViewErrorBag;

/**
 * Formatting shared by the admin Blade components.
 */
trait FormatsPanels
{
    public function money(?string $currency, mixed $amount): string
    {
        return is_numeric($amount) ? trim(($currency ?? '').' '.number_format((float) $amount, 2)) : '';
    }

    public function date(?string $value, bool $withTime = true): string
    {
        $time = $value ? strtotime($value) : false;

        return $time ? date($withTime ? 'j M Y, H:i' : 'j M Y', $time) : '';
    }

    /**
     * attention, info, good, bad or neutral, for a Flutterwave status.
     */
    public function tone(?string $status): string
    {
        return match (true) {
            in_array(strtolower((string) $status), ['successful', 'completed', 'settled', 'success'], true), str_starts_with(strtolower((string) $status), 'completed') => 'good',
            in_array(strtolower((string) $status), ['failed', 'flagged', 'error', 'cancelled'], true) => 'bad',
            in_array(strtolower((string) $status), ['pending', 'new', 'processing'], true) => 'attention',
            default => 'neutral',
        };
    }

    /** This page's URL with some query parameters changed; null removes one. */
    public function urlWith(array $params): string
    {
        return request()->fullUrlWithQuery($params);
    }

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
}
