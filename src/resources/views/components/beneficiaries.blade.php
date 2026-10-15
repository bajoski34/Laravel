@include('flutterwave::components.partials.styles')
<div {{ $attributes->merge(['class' => 'flw']) }}>
    @if (session('flutterwave.status'))
        <p class="flw-notice flw-notice--success" role="status">{{ session('flutterwave.status') }}</p>
    @endif
    @if ($error = $firstError(['flutterwave', 'account_bank', 'account_number', 'beneficiary_name']))
        <p class="flw-notice flw-notice--error" role="alert">{{ $error }}</p>
    @endif

    @if ($panel['urls'])
        <details class="flw-details" @if ($panel['items'] === [] || $error) open @endif>
            <summary>Add a beneficiary</summary>
            <form method="POST" action="{{ $panel['urls']['create'] }}" class="flw-card flw-card--narrow">
                @csrf
                <div class="flw-field">
                    <span class="flw-field__label">Bank</span>
                    <x-flutterwave-bank-select name="account_bank" :banks="$panel['banks']" :selected="old('account_bank')" />
                    @if ($panel['banks_error'])
                        <span class="flw-field__error">{{ $panel['banks_error'] }}</span>
                    @endif
                </div>
                <label class="flw-field"><span class="flw-field__label">Account number</span><input class="flw-input" name="account_number" required inputmode="numeric" maxlength="20" value="{{ old('account_number') }}"></label>
                <label class="flw-field">
                    <span class="flw-field__label">Name</span>
                    <input class="flw-input" name="beneficiary_name" maxlength="100" value="{{ old('beneficiary_name') }}">
                    <span class="flw-field__hint">Leave empty to use the name on the account.</span>
                </label>
                <button type="submit" class="flw-btn flw-btn--primary flw-btn--block">Save beneficiary</button>
            </form>
        </details>
    @endif

    @if ($panel['error'])
        <p class="flw-notice flw-notice--error">Couldn’t load beneficiaries: {{ $panel['error'] }}</p>
    @elseif ($panel['items'] === [])
        <p class="flw-text">No beneficiaries yet. Saved recipients make repeat transfers quicker.</p>
    @else
        <ul class="flw-rows">
            @foreach ($panel['items'] as $beneficiary)
                <li>
                    <div class="flw-row" style="align-items: center">
                        <div class="flw-stack">
                            <span class="flw-strong">{{ $beneficiary['full_name'] }}</span>
                            <span class="flw-meta"><span>{{ $beneficiary['account_number'] }}</span><span>{{ $beneficiary['bank_name'] }} ({{ $beneficiary['bank_code'] }})</span></span>
                        </div>
                        @if ($beneficiary['urls'])
                            <form method="POST" action="{{ $beneficiary['urls']['delete'] }}" onsubmit="return confirm('Delete {{ addslashes((string) $beneficiary['full_name']) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flw-btn flw-btn--outline flw-btn--sm">Delete</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @include('flutterwave::components.pager', ['page' => $panel['page'], 'urlWith' => $urlWith])
</div>
