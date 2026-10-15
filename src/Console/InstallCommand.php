<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    public const REPOSITORY = 'https://github.com/bajoski34/Laravel';

    protected $signature = 'flutterwave:install
        {--force : Overwrite files that were already published}
        {--react : Also publish the React components for Inertia apps}
        {--vue : Also publish the Vue 3 components for Inertia apps}
        {--svelte : Also publish the Svelte 5 components for Inertia apps}
        {--billable : Also publish the migration for saving customers\' cards}';

    protected $description = 'Publish the Flutterwave config and add the required keys to your .env file';

    private const ENV_KEYS = [
        'FLW_PUBLIC_KEY' => 'Public key from https://app.flutterwave.com/dashboard/settings/apis/live',
        'FLW_SECRET_KEY' => 'Secret key from the same page',
        'FLW_SECRET_HASH' => 'Any random string; paste the same value into Settings > Webhooks',
        'FLW_ENCRYPTION_KEY' => 'Encryption key from the API settings page',
    ];

    public function handle(): int
    {
        $frameworks = array_values(array_filter(['react', 'vue', 'svelte'], fn (string $framework) => $this->option($framework)));

        if (count($frameworks) > 1) {
            $this->error('Pick one of --react, --vue or --svelte: they publish to the same folder.');

            return self::FAILURE;
        }

        $this->callSilent('vendor:publish', [
            '--tag' => 'flutterwave-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->info('Published config/flutterwave.php');

        $this->addEnvKeys();

        if ($frameworks !== []) {
            $this->publishComponents($frameworks[0]);
        }

        if ($this->option('billable')) {
            $this->publishBillableMigration();
        }

        if (config('flutterwave.webhook.enabled', true)) {
            $this->newLine();
            $this->line('Set this as your webhook URL on the Flutterwave dashboard (Settings > Webhooks):');
            $this->line('  <comment>'.url(config('flutterwave.webhook.path', 'flutterwave/webhook')).'</comment>');
        }

        $this->newLine();
        $this->info('Flutterwave is ready. Try it out:');
        $this->line("  return Flutterwave::redirect(['amount' => 5000, 'email' => 'jane@example.com']);");

        if ($this->input->isInteractive() && $this->confirm('Would you like to star the repo on GitHub? It helps other developers find it.', true)) {
            $this->openInBrowser(self::REPOSITORY);
        }

        return self::SUCCESS;
    }

    private function publishBillableMigration(): void
    {
        if (glob(database_path('migrations/*_add_flutterwave_card_columns.php')) !== []) {
            $this->info('The saved card migration is already in database/migrations.');

            return;
        }

        $this->callSilent('vendor:publish', ['--tag' => 'flutterwave-migrations']);
        $this->info('Published the saved card migration. Run php artisan migrate, then add the Flutterwave\\Payments\\Billable trait to your User model.');
    }

    private function publishComponents(string $framework): void
    {
        $this->callSilent('vendor:publish', [
            '--tag' => "flutterwave-{$framework}",
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info("Published the {$framework} components to resources/js/components/flutterwave");
        $this->line("  import { FlutterwavePayButton, VirtualAccount } from '@/components/flutterwave';");
    }

    private function addEnvKeys(): void
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path)) {
            $this->warn('No .env file found. Add these keys yourself: '.implode(', ', array_keys(self::ENV_KEYS)));

            return;
        }

        $contents = (string) file_get_contents($path);
        $missing = array_filter(
            array_keys(self::ENV_KEYS),
            static fn (string $key) => ! preg_match('/^'.preg_quote($key, '/').'=/m', $contents)
        );

        if ($missing === []) {
            $this->info('Your .env file already has the Flutterwave keys.');

            return;
        }

        if (! $this->confirm('Add '.implode(', ', $missing).' to your .env file?', true)) {
            return;
        }

        $lines = [];
        foreach ($missing as $key) {
            $lines[] = '# '.self::ENV_KEYS[$key];
            $lines[] = "{$key}=";
        }

        file_put_contents($path, rtrim($contents, "\n")."\n\n".implode("\n", $lines)."\n");
        $this->info('Added the Flutterwave keys to .env. Fill in their values to go live.');
    }

    private function openInBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => 'open',
            'Windows' => 'start ""',
            default => 'xdg-open',
        };

        exec($command.' '.escapeshellarg($url));
    }
}
