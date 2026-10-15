<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment successful</title>
    @include('flutterwave::components.partials.styles')
    <style>body { margin: 0; background: #fff; } .flw-result { justify-items: center; margin: 0 auto; max-width: 480px; padding: 18vh 20px 80px; text-align: center; } .flw-result h1 { color: var(--flw-orange); font-size: 24px; }</style>
</head>
<body>
<main class="flw flw-result">
    <span class="flw-pill" data-tone="good">{{ request('tx_ref') ?: 'Payment successful' }}</span>
    <h1 class="flw-heading">Payment successful</h1>
    <p class="flw-text">Thanks, your payment went through. You'll get a receipt by email.</p>
    <a class="flw-btn flw-btn--primary" href="{{ url('/') }}">Back to {{ config('app.name') }}</a>
</main>
</body>
</html>
