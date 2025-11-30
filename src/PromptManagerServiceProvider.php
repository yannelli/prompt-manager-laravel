<?php

namespace Yannelli\PromptManager;

use Illuminate\Pipeline\Pipeline;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Yannelli\PromptManager\Contracts\PromptRendererInterface;
use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Renderers\SimpleRenderer;
use Yannelli\PromptManager\Versioning\VersionManager;

class PromptManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('prompt-manager')
            ->hasConfigFile()
            ->hasMigrations([
                'create_prompt_templates_table',
                'create_prompt_template_versions_table',
                'create_prompt_components_table',
                'create_prompt_executions_table',
            ]);
    }

    public function packageRegistered(): void
    {
        // Bind the main PromptManager service
        $this->app->singleton(PromptManager::class, function ($app) {
            return new PromptManager($app->make(Pipeline::class));
        });

        // Bind the version resolver
        $this->app->singleton(VersionResolverInterface::class, function ($app) {
            return new VersionManager;
        });

        // Bind the renderer based on config
        $this->app->bind(PromptRendererInterface::class, function ($app) {
            $renderer = config('prompt-manager.renderer', 'simple');
            $rendererClass = config("prompt-manager.renderers.{$renderer}");

            if ($rendererClass && class_exists($rendererClass)) {
                return $app->make($rendererClass);
            }

            return new SimpleRenderer;
        });

        // Register type handlers
        $this->registerTypeHandlers();
    }

    public function packageBooted(): void
    {
        // Register facade alias
        $this->app->alias(PromptManager::class, 'prompt-manager');
    }

    protected function registerTypeHandlers(): void
    {
        $types = config('prompt-manager.types', []);

        foreach ($types as $key => $typeClass) {
            $this->app->bind("prompt-manager.type.{$key}", function ($app) use ($typeClass) {
                return $app->make($typeClass);
            });
        }
    }
}
