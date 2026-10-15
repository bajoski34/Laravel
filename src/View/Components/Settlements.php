<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components;

use Flutterwave\Payments\View\Components\Concerns\FormatsPanels;
use Illuminate\View\Component;

/**
 * <x-flutterwave-settlements :panel="Flutterwave::settlements()->panel(request()->query())" />
 */
class Settlements extends Component
{
    use FormatsPanels;

    public function __construct(public array $panel) {}

    public function render()
    {
        return view('flutterwave::components.settlements');
    }
}
