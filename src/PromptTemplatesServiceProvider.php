<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates;

use PromptManager\PromptTemplates\Commands\InstallCommand;
use PromptManager\PromptTemplates\Commands\MakeHandlerCommand;
use PromptManager\PromptTemplates\Commands\MakeMapperCommand;
use PromptManager\PromptTemplates\Commands\SeedTypesCommand;
use PromptManager\PromptTemplates\Pipelines\PipelineExecutor;
use PromptManager\PromptTemplates\Renderers\PromptRenderer;
use PromptManager\PromptTemplates\Services\ComponentManager;
use PromptManager\PromptTemplates\Services\VersionManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PromptTemplatesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('prompt-templates')
            ->hasConfigFile()
            ->hasMigrations([
                'create_prompt_types_table',
                'create_prompt_templates_table',
                'create_prompt_template_versions_table',
                'create_prompt_components_table',
                'create_prompt_template_components_table',
                'create_prompt_pipelines_table',
                'create_prompt_pipeline_steps_table',
            ])
            ->hasCommands([
                InstallCommand::class,
                MakeHandlerCommand::class,
                MakeMapperCommand::class,
                SeedTypesCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register core services
        $this->app->singleton('prompt-templates.renderer', function ($app) {
            return new PromptRenderer;
        });

        $this->app->singleton('prompt-templates.pipeline', function ($app) {
            return new PipelineExecutor;
        });

        $this->app->singleton('prompt-templates.versions', function ($app) {
            return new VersionManager;
        });

        $this->app->singleton('prompt-templates.components', function ($app) {
            return new ComponentManager;
        });

        // Bind classes
        $this->app->bind(PromptRenderer::class, function ($app) {
            return $app->make('prompt-templates.renderer');
        });

        $this->app->bind(PipelineExecutor::class, function ($app) {
            return $app->make('prompt-templates.pipeline');
        });

        $this->app->bind(VersionManager::class, function ($app) {
            return $app->make('prompt-templates.versions');
        });

        $this->app->bind(ComponentManager::class, function ($app) {
            return $app->make('prompt-templates.components');
        });
    }

    public function packageBooted(): void
    {
        // Register version mappers from config
        $mappers = config('prompt-templates.version_mappers', []);

        if (! empty($mappers)) {
            $versionManager = $this->app->make(VersionManager::class);

            foreach ($mappers as $name => $mapperClass) {
                if (class_exists($mapperClass)) {
                    $versionManager->registerMapper($name, $this->app->make($mapperClass));
                }
            }
        }
    }
}
