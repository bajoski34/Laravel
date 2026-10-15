<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * <x-flutterwave-bank-select name="account_bank" :selected="old('account_bank')" />
 *
 * A dropdown of the country's banks: each option shows the bank's name and submits its code.
 * The list comes from banks()->options() (cached for a day) unless you pass :banks.
 */
class BankSelect extends Component
{
    public string $componentId;

    public ?string $error = null;

    public function __construct(
        public string $name = 'account_bank',
        public ?array $banks = null,
        public ?string $country = null,
        public ?string $selected = null,
        public bool $required = true,
        public bool $searchable = true,
        public string $placeholder = 'Choose a bank',
        public ?string $id = null,
    ) {
        $this->componentId = $id ?? 'flw-bank-'.Str::lower(Str::random(8));

        if ($this->banks === null) {
            try {
                $this->banks = app('flutterwave')->banks()->options($country ?? (string) config('flutterwave.country', 'NG'));
            } catch (FlutterwaveException $e) {
                $this->banks = [];
                $this->error = "Couldn't load the list of banks: ".$e->getMessage();
            }
        }
    }

    public function render()
    {
        return view('flutterwave::components.bank-select');
    }
}
