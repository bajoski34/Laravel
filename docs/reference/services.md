# Services

Each Flutterwave API area is a service class, reached through the facade. Methods return Flutterwave's decoded JSON response as an array.

```php
use Flutterwave\Payments\Facades\Flutterwave;

Flutterwave::transfers()->create([...]);
Flutterwave::use('transfers')->create([...]);   // same thing, by config key
```

## Errors

When the API returns an error, methods throw `Flutterwave\Payments\Exception\FlutterwaveException` (or its `NetworkConnection` subclass when Flutterwave can't be reached):

```php
use Flutterwave\Payments\Exception\FlutterwaveException;

try {
    Flutterwave::transfers()->create($payload);
} catch (FlutterwaveException $e) {
    $e->getMessage();      // Flutterwave's message
    $e->getStatusCode();   // HTTP status
    $e->getResponse();     // the full error body
}
```

The exception is the transactions service: verifying, listing, refunding and fee lookups return the error body instead, so check `$response['status'] === 'success'`.

You can [replace or turn off](/guide/configuration#swapping-or-turning-off-services) any service in the config.

## Explorer

<ServiceExplorer />

## At a glance

<!--@include: ../.vitepress/data/services.md-->
