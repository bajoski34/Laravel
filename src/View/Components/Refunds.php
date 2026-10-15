<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components;

use Flutterwave\Payments\View\Components\Concerns\FormatsPanels;
use Illuminate\View\Component;

/**
 * <x-flutterwave-refunds :panel="Flutterwave::refunds()->panel(request()->query())" />
 */
class Refunds extends Component
{
    use FormatsPanels;

    public function __construct(public array $panel) {}

    public function render()
    {
        return view('flutterwave::components.refunds');
    }
}
