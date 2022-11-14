<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Exception;
use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Exception\NetworkConnection;
use Flutterwave\Payments\Exception\ServiceNotFound;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class Modal
{
    private ?string $publicKey;

    private ?string $secretKey;

    private string $redirectUrl;

    private string $title;

    private string $description;

    private string $logo;

    private string $country;

    private string $secret_hash;

    private string $env;

    private string $encryption_key;

    private Api $api;

    private string $currency;

    private string $success_url;

    private string $cancel_url;

    private string $payment_options;

    private string $business_name;

    private LoggerInterface $logger;

    /**
     * Modal constructor.
     *
     * @param  array  $config
     */
    public function __construct(Api $api, array $config)
    {
        $this->logger = Log::channel('flutterwave');
        $this->publicKey = $config['public_key'] ?? null;
        $this->secretKey = $config['secret_key'] ?? null;
        $this->redirectUrl = $config['redirect_url'];
        $this->title = $config['title'];
        $this->description = $config['description'];
        $this->logo = $config['logo'];
        $this->country = $config['country'];
        $this->currency = $config['currency'];
        $this->payment_options = implode(',', $config['payment_options']); //O(n)
        $this->env = $config['env'];
        $this->secret_hash = $config['secret_hash'];
        $this->encryption_key = $config['encryption_key'];
        $this->business_name = $config['business_name'];
        $this->success_url = $config['success_url'];
        $this->cancel_url = $config['cancel_url'];
        $this->api = $api;
    }

    /**
     * Render Inline or Standard Modal for payment
     * @param array $data
     * @param string $type
     * @return string
     * @throws Exception
     */
    public function render(array $data, string $type = 'inline'): string
    {
        if ($type !== 'inline') {
            return $this->standardRequest($data);
        }

        $data = $this->addSettings($data);
        $this->validateRequest($data);

        return \json_encode([
            'public_key' => $this->publicKey,
            'payment_options' => $this->payment_options,
            ...$data,
        ]);
    }

    public static function displayInline(array $data): \Illuminate\Contracts\View\View
    {
        return view('flutterwave::modal', compact('data'));
    }

    private function addSettings(array $data): array
    {
        return [
            ...$data,
            'currency' => $data['currency'] ?? $this->currency,
            'redirect_url' => $data['redirect_url'] ?? $this->redirectUrl,
            'customizations' => [
                'title' => $this->title,
                'description' => $this->description,
                'logo' => $this->logo,
            ],
        ];
    }

    private function validateRequest(array $request): void
    {
        $required = ['amount', 'customer'];

        foreach ($required as $key) {
            if (! isset($request[$key])) {
                $this->logger->notice("Flutterwave Modal::Missing required field {$key} [standard request]");
                throw new InvalidArgument("Missing required field {$key}");
            }
        }
    }

    private function handleResponse($response): void
    {
        if ($response->serverError()) {
            throw new ServiceNotFound('This service is currently unavailable');
        }
    }

    /**
     * @throws Exception
     */
    private function standardRequest(array $request): string
    {
        $baseUrl = $this->api::BASE_URL;
        $specific_route = $this->api::STANDARD_ENDPOINT;
        $apiVersion = $this->api::LATEST_VERSION;

        $this->logger->info("Flutterwave::Generated Payment Link [{$specific_route}]");
        $request = $this->addSettings($request);
        $this->validateRequest($request);

        try {
            $response = Http::withToken($this->secretKey)->post("{$baseUrl}/{$apiVersion}/{$specific_route}", $request);
        } catch(ConnectionException $e) {
            throw new NetworkConnection('please check your network connection. Unable to connect to Flutterwave APIs.');
        }

        $this->handleResponse($response);

        return $response->json()['data']['link'];
    }
}
