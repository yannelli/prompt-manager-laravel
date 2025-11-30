<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use PromptManager\PromptTemplates\PromptTemplatesServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'PromptManager\\PromptTemplates\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            PromptTemplatesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        $migration = include __DIR__.'/../database/migrations/create_prompt_types_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_prompt_templates_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_prompt_template_versions_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_prompt_components_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_prompt_template_components_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_prompt_pipelines_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_prompt_pipeline_steps_table.php.stub';
        $migration->up();
    }
}
