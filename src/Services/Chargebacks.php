<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Services;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Illuminate\Support\Facades\Route;
use SplFileInfo;

/**
 * Chargebacks raised by customers' banks against your transactions.
 *
 * Respond before the due date (10 working hours for local transactions, 30 for
 * international), or the chargeback is lost and the customer is refunded.
 *
 * @see https://developer.flutterwave.com/docs/chargebacks
 */
class Chargebacks extends Service
{
    /** Proof must be a single JPEG, PNG or PDF file under 8MB. */
    public const PROOF_TYPES = ['jpg', 'jpeg', 'png', 'pdf'];

    public const MAX_PROOF_BYTES = 8 * 1024 * 1024;

    /**
     * Filters: page, status (initiated, accepted, declined, won, lost), from and to
     * (YYYY-MM-DD), currency, flw_ref, id.
     */
    public function all(array $filters = []): array
    {
        return $this->client->get('chargebacks', $filters);
    }

    /**
     * Fetch a chargeback, including its history, by the transaction's flw_ref.
     * Like all(), the response's `data` is a list.
     */
    public function find(string $flwRef): array
    {
        return $this->client->get('chargebacks', ['flw_ref' => $flwRef]);
    }

    /**
     * Accept the chargeback: the customer gets their money back.
     */
    public function accept(int|string $id, string $comment): array
    {
        return $this->respond($id, ['action' => 'accept', 'comment' => $comment]);
    }

    /**
     * Dispute the chargeback with proof that the customer received value.
     * Get the proof link from uploadProof(), or use declineWithProof() to do both.
     */
    public function decline(int|string $id, string $comment, string $proofLink): array
    {
        if (trim($proofLink) === '') {
            throw new InvalidArgument('Declining a chargeback needs a proof link. Upload the evidence with uploadProof() first.');
        }

        return $this->respond($id, ['action' => 'decline', 'comment' => $comment, 'prooflink' => $proofLink]);
    }

    /**
     * Upload evidence for a dispute. Combine all your documents into one file.
     * Accepts a file path, or a file object such as an UploadedFile from a form.
     * The response's `data` is the proof link to pass to decline().
     */
    public function uploadProof(string|SplFileInfo $file): array
    {
        [$path, $filename] = $this->proofFile($file);

        return $this->client->upload('upload_image', 'image', (string) file_get_contents($path), $filename);
    }

    /**
     * Upload the evidence and decline the chargeback in one call.
     */
    public function declineWithProof(int|string $id, string $comment, string|SplFileInfo $file): array
    {
        $this->requireComment($comment);

        return $this->decline($id, $comment, (string) ($this->uploadProof($file)['data'] ?? ''));
    }

    /**
     * Everything the chargeback components need, as one array: e.g. an Inertia prop
     * or the :panel of <x-flutterwave-chargebacks>. API errors come back in `error`
     * instead of being thrown, so a failed lookup doesn't break the page.
     */
    public function panel(array $filters = []): array
    {
        try {
            $response = $this->all($filters);
            $error = null;
        } catch (FlutterwaveException $e) {
            $response = [];
            $error = $e->getMessage();
        }

        return [
            'items' => array_map(fn (array $chargeback) => $this->present($chargeback), $response['data'] ?? []),
            'page' => $response['meta']['page_info'] ?? null,
            'error' => $error,
            'proof' => ['types' => self::PROOF_TYPES, 'max_bytes' => self::MAX_PROOF_BYTES],
        ];
    }

    private function present(array $chargeback): array
    {
        $status = strtolower((string) ($chargeback['status'] ?? ''));
        $due = strtotime((string) ($chargeback['due_date'] ?? '')) ?: null;
        $open = in_array($status, ['initiated', 'pending'], true);
        $canRespond = $open && Route::has('flutterwave.chargebacks.accept');

        return [
            'id' => $chargeback['id'] ?? null,
            'amount' => $chargeback['amount'] ?? null,
            'status' => $status,
            'stage' => $chargeback['stage'] ?? null,
            'comment' => $chargeback['comment'] ?? null,
            'flw_ref' => $chargeback['flw_ref'] ?? null,
            'tx_ref' => $chargeback['tx_ref'] ?? null,
            'transaction_id' => $chargeback['transaction_id'] ?? null,
            'due_at' => $due ? date(DATE_ATOM, $due) : null,
            // Seconds left to respond, counted on the server so the browser's clock doesn't matter.
            'respond_within' => $open && $due ? max(0, $due - time()) : null,
            'proof' => $chargeback['meta']['uploaded_proof'] ?? null,
            'history' => array_map(static fn (array $entry) => [
                'action' => $entry['action'] ?? null,
                'date' => $entry['date'] ?? null,
                'description' => $entry['description'] ?? null,
                'comment' => $entry['comment'] ?? null,
            ], $chargeback['meta']['history'] ?? []),
            'urls' => $canRespond ? [
                'accept' => route('flutterwave.chargebacks.accept', $chargeback['id']),
                'decline' => route('flutterwave.chargebacks.decline', $chargeback['id']),
            ] : null,
        ];
    }

    private function respond(int|string $id, array $payload): array
    {
        $this->requireComment($payload['comment']);

        return $this->client->put("chargebacks/{$id}", $payload);
    }

    private function requireComment(string $comment): void
    {
        if (trim($comment) === '') {
            throw new InvalidArgument('Flutterwave needs a comment explaining why you are accepting or declining the chargeback.');
        }
    }

    /**
     * @return array{0: string, 1: string} the file's path and the name to upload it as
     */
    private function proofFile(string|SplFileInfo $file): array
    {
        $path = $file instanceof SplFileInfo ? $file->getPathname() : $file;

        // A form upload is stored under a random temp name; its original name carries the extension.
        $filename = $file instanceof SplFileInfo && method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : basename($path);

        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgument("The chargeback proof file {$path} doesn't exist or can't be read.");
        }

        if (! in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::PROOF_TYPES, true)) {
            throw new InvalidArgument('Chargeback proof must be a JPEG, PNG or PDF file, not '.$filename.'.');
        }

        if (filesize($path) >= self::MAX_PROOF_BYTES) {
            throw new InvalidArgument('Chargeback proof must be smaller than 8MB. Compress the file, or merge fewer pages into it.');
        }

        return [$path, $filename];
    }
}
