<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use function json_encode;

final class Modal
{
    private string $publicKey;
    private string $secretKey;
    private ?string $redirectUrl;
    private ?string $title;
    private ?string $description;
    private ?string $logo;
    private ?string $country;
    private ?string $secret_hash;
    private ?string $env;
    /**
     * @var string|null
     */
    private string $encryption_key;

    /**
     * Modal constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->publicKey = $config['public_key'];
        $this->secretKey = $config['secret_key'];
        $this->redirectUrl = $config['redirect_url'] ?? null;
        $this->title = $config['title'] ?? null;
        $this->description = $config['description'] ?? null;
        $this->logo = $config['logo'] ?? null;
        $this->country = $config['country'] ?? null;
        $this->env = $config['env'] ?? null;
        $this->secret_hash = $config['secret_hash'] ?? null;
        $this->encryption_key = $config['encryption_key'] ?? null;
        $this->business_name = $config['business_name'] ?? null;
        $this->success_url = $config['success_url'] ?? null;
        $this->cancel_url = $config['cancel_url'] ?? null;
    }

    /**
     * @param array $data
     *
     * @return string|View
     *
     * @throws Exception
     */
    public function getData(array $data, string $type = 'inline'): string
    {
        if ($type !== 'inline') {
            return $this->standardRequest($data);
        }

        $data = $this->addSettings($data);

        $validation = $this->validateRequest($data);

        if (! is_bool($validation)) {
            return $validation;
        }

        return json_encode([
            'public_key' => $this->publicKey,
            ...$data,
        ]);
    }

    private function addSettings(array $data): array
    {
        return [
            ...$data,
            'redirect_url' => $data['redirect_url'] ?? $this->redirectUrl,
            'customizations' => [
                'title' => $this->title,
                'description' => $this->description,
                'logo' => $this->logo,
            ],
        ];
    }

    private function validateRequest(array $request): string|bool
    {
        $required = [
            'tx_ref',
            'amount',
            'currency',
            'redirect_url',
        ];

        foreach ($required as $key) {
            if (! isset($request[$key])) {
                Log::notice("Flutterwave::Missing required field {$key} [standard request]");
                return route('flutterwave.error', ['message' => "Missing required field {$key}"]);
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    private function standardRequest(array $request): string
    {
        $request = $this->addSettings($request);

        $validation = $this->validateRequest($request);
        if (! is_bool($validation)) {
            return $validation;
        }

        $response = Http::withToken($this->secretKey)->post('https://api.flutterwave.com/v3/payments', $request);
        return $response->json()['data']['link'];
    }
}
