<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Verify a Nigerian customer's BVN with their consent: request consent, send the customer
 * to the returned NIBSS page, then fetch the result with the reference.
 *
 * The result holds personal data, including a photo of the customer's face (bvn_data.faceImage).
 * Store only what you need and never log it. The package doesn't log successful responses.
 *
 * @see https://developer.flutterwave.com/reference/initiate-consent-request-for-bvn-verification
 */
class Bvn extends Service
{
    /**
     * Returns `data.url` (send the customer there to give consent) and `data.reference` (keep it).
     */
    public function requestConsent(string $bvn, string $firstName, string $lastName, ?string $redirectUrl = null): array
    {
        if (! preg_match('/^\d{11}$/', $bvn)) {
            throw new InvalidArgument('A BVN is 11 digits.');
        }

        return $this->client->post('bvn/verifications', $this->filter([
            'bvn' => $bvn,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'redirect_url' => $redirectUrl,
        ]));
    }

    /**
     * `data.status` is COMPLETED once the customer has consented; `data.bvn_data` then holds their details.
     */
    public function verification(string $reference): array
    {
        return $this->client->get("bvn/verifications/{$reference}", ['include_complete_message' => 1]);
    }

    /**
     * True once the customer has consented and the BVN's names match the ones you have,
     * ignoring case and the order of first and last name.
     */
    public function matches(string $reference, string $firstName, string $lastName): bool
    {
        $data = $this->verification($reference)['data'] ?? [];

        if (($data['status'] ?? null) !== 'COMPLETED') {
            return false;
        }

        // Compare against the BVN record only. The top-level first_name and last_name are the
        // names sent with the consent request, so matching them would always succeed.
        $bvn = $data['bvn_data'] ?? [];

        if (empty($bvn['firstName']) || empty($bvn['surname'])) {
            return false;
        }

        $normalize = static fn (array $names) => collect($names)->map(fn ($name) => strtolower(trim((string) $name)))->filter()->sort()->values()->all();

        return $normalize([$bvn['firstName'], $bvn['surname']]) === $normalize([$firstName, $lastName]);
    }
}
