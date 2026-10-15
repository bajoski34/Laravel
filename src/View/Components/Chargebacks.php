<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components;

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;

/**
 * <x-flutterwave-chargebacks :panel="Flutterwave::chargebacks()->panel(['status' => 'initiated'])" />
 *
 * Lists chargebacks with forms to accept or dispute them. The forms post to the
 * routes from Route::flutterwaveAdmin(); without them the list is read-only.
 */
class Chargebacks extends Component
{
    public function __construct(public array $panel) {}

    public static function tone(string $status): array
    {
        return match ($status) {
            'initiated', 'pending' => ['Needs a response', 'attention'],
            'accepted' => ['Accepted, customer refunded', 'neutral'],
            'declined' => ['Disputed, awaiting the bank', 'info'],
            'won' => ['Won', 'good'],
            'lost' => ['Lost, customer refunded', 'bad'],
            'reversed' => ['Reversed, funds restored', 'good'],
            default => [$status, 'neutral'],
        };
    }

    public static function deadline(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return match (true) {
            $days > 0 => $days.' '.($days === 1 ? 'day' : 'days').($hours ? " {$hours} h" : ''),
            $hours > 0 => "{$hours} h".($minutes ? " {$minutes} min" : ''),
            default => max(1, $minutes).' min',
        };
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
        return view('flutterwave::components.chargebacks');
    }
}
