<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Illuminate\Console\Command;

class ListBranchesCommand extends Command
{
    protected $signature = 'flutterwave:branches
        {bank : Bank id (the id column from flutterwave:banks, not the code)}
        {--search= : Only show branches whose name contains this text}';

    protected $description = 'List branch codes for a bank';

    public function handle(): int
    {
        $branches = $this->laravel->make('flutterwave')->banks()->branches((string) $this->argument('bank'))['data'] ?? [];
        $search = $this->option('search');

        if ($search) {
            $branches = array_filter($branches, static fn (array $branch) => stripos($branch['branch_name'] ?? '', (string) $search) !== false);
        }

        $this->table(['Code', 'Name', 'Swift code'], array_map(static fn (array $branch) => [
            $branch['branch_code'] ?? '',
            $branch['branch_name'] ?? '',
            $branch['swift_code'] ?? '',
        ], $branches));

        return self::SUCCESS;
    }
}
