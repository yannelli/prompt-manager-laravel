<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'prompt-templates:install
                            {--migrate : Run migrations after installation}
                            {--seed : Seed default prompt types after installation}';

    protected $description = 'Install the Prompt Templates package';

    public function handle(): int
    {
        $this->info('Installing Prompt Templates...');

        // Publish config
        $this->callSilent('vendor:publish', [
            '--tag' => 'prompt-templates-config',
        ]);
        $this->info('✓ Configuration file published.');

        // Publish migrations
        $this->callSilent('vendor:publish', [
            '--tag' => 'prompt-templates-migrations',
        ]);
        $this->info('✓ Migration files published.');

        // Run migrations if requested
        if ($this->option('migrate')) {
            $this->call('migrate');
            $this->info('✓ Migrations executed.');
        }

        // Seed default types if requested
        if ($this->option('seed')) {
            $this->call('prompt-templates:seed-types');
        }

        $this->newLine();
        $this->info('Prompt Templates has been installed successfully!');
        $this->newLine();

        $this->line('Next steps:');
        $this->line('  1. Run migrations: php artisan migrate');
        $this->line('  2. Seed default types: php artisan prompt-templates:seed-types');
        $this->line('  3. Create your first template!');

        return self::SUCCESS;
    }
}
