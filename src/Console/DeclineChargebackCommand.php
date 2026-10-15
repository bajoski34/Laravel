<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Console\Concerns\HandlesApiErrors;
use Flutterwave\Payments\Support\Audit;
use Illuminate\Console\Command;

class DeclineChargebackCommand extends Command
{
    use HandlesApiErrors;

    protected $signature = 'flutterwave:decline-chargeback
        {id : Chargeback id, from flutterwave:chargebacks}
        {--proof= : Your evidence as one JPEG, PNG or PDF file under 8MB}
        {--proof-link= : A proof link you already uploaded, instead of --proof}
        {--comment= : What the evidence shows}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Dispute a chargeback with proof that the customer received value';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $proof = $this->option('proof');
        $link = $this->option('proof-link');

        if (! $proof === ! $link) {
            $this->error('Pass either --proof with your evidence file or --proof-link with an uploaded proof.');

            return self::FAILURE;
        }

        $comment = (string) ($this->option('comment') ?? $this->ask('What does your evidence show?'));

        if (! $this->confirmChange("Dispute chargeback {$id} with ".($proof ? basename((string) $proof) : 'this proof link').'?')) {
            return self::FAILURE;
        }

        $chargebacks = $this->laravel->make('flutterwave')->chargebacks();
        $response = $this->attempt(fn () => $proof
            ? $chargebacks->declineWithProof($id, $comment, (string) $proof)
            : $chargebacks->decline($id, $comment, (string) $link));

        Audit::record('chargeback.decline', ['chargeback_id' => $id, 'proof' => $proof ? basename((string) $proof) : $link], $response !== null);

        if ($response === null) {
            return self::FAILURE;
        }

        $this->info($response['message'] ?? 'Chargeback declined.');
        $this->line('Flutterwave emails you as the dispute moves on. Check it with flutterwave:chargebacks --ref='.($response['data']['flw_ref'] ?? '<flw_ref>'));

        return self::SUCCESS;
    }
}
