<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    public const REPOSITORY = 'https://github.com/bajoski34/Laravel';

    protected $signature = 'flutterwave:install {--force : Overwrite an existing config/flutterwave.php}';

    protected $description = 'Publish the Flutterwave config and add the required keys to your .env file';

    private const ENV_KEYS = [
        'FLW_PUBLIC_KEY' => 'Public key from https://app.flutterwave.com/dashboard/settings/apis',
        'FLW_SECRET_KEY' => 'Secret key from the same page',
        'FLW_SECRET_HASH' => 'Any random string; paste the same value into Settings > Webhooks',
        'FLW_ENCRYPTION_KEY' => 'Encryption key from the API settings page',
    ];

    public function handle(): int
    {
        $this->callSilent('vendor:publish', [
            '--tag' => 'flutterwave-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->info('Published config/flutterwave.php');

        $this->addEnvKeys();

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
