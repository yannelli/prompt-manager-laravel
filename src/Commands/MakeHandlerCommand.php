<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeHandlerCommand extends GeneratorCommand
{
    protected $signature = 'prompt-templates:make-handler {name : The name of the handler class}';

    protected $description = 'Create a new prompt type handler class';

    protected $type = 'TypeHandler';

    protected function getStub(): string
    {
        return __DIR__.'/../../resources/stubs/type-handler.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\PromptHandlers';
    }

    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        return str_replace('{{ class }}', class_basename($name), $stub);
    }
}
