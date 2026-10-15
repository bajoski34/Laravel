@include('flutterwave::components.partials.styles')
<div id="{{ $componentId }}" {{ $attributes->merge(['class' => 'flw-bank-select']) }}>
    @if ($error)
        <p class="flw-error-text">{{ $error }}</p>
    @endif
    @if ($searchable && count($banks) > 12)
        <input type="search" class="flw-input" data-flw-bank-search placeholder="Search {{ count($banks) }} banks" aria-label="Search banks" aria-controls="{{ $componentId }}-select" autocomplete="off">
    @endif
    <div class="flw-select">
        <select id="{{ $componentId }}-select" class="flw-input" name="{{ $name }}" @if ($required) required @endif @if ($banks === []) disabled @endif>
            <option value="">{{ $placeholder }}</option>
            @foreach ($banks as $bank)
                <option value="{{ $bank['code'] }}" @selected((string) $selected === (string) $bank['code'])>{{ $bank['name'] }}</option>
            @endforeach
        </select>
        @include('flutterwave::components.partials.chevron')
    </div>
</div>
@if ($searchable && count($banks) > 12)
<script>
    (function () {
        var root = document.getElementById(@json($componentId));
        var search = root.querySelector('[data-flw-bank-search]');
        var select = root.querySelector('select');
        var all = Array.prototype.slice.call(select.options, 1).map(function (option) { return [option.value, option.text]; });

        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            var current = select.value;
            while (select.options.length > 1) select.remove(1);
            all.forEach(function (bank) {
                if (term === '' || bank[1].toLowerCase().indexOf(term) !== -1 || bank[0].indexOf(term) === 0 || bank[0] === current) {
                    select.add(new Option(bank[1], bank[0], false, bank[0] === current));
                }
            });
            if (select.options.length === 2 && current === '') select.selectedIndex = 1;
        });
    })();
</script>
@endif
