{{-- Hidden inputs for the page's other query parameters, so a GET filter form keeps e.g. the admin page's section. --}}
@foreach (request()->except(array_merge($except ?? [], ['page'])) as $key => $value)
    @if (is_scalar($value))
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endif
@endforeach
