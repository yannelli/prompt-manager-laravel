<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeMapperCommand extends GeneratorCommand
{
    protected $signature = 'prompt-templates:make-mapper {name : The name of the mapper class}
                            {--from=1 : Source version number}
                            {--to=2 : Target version number}';

    protected $description = 'Create a new version mapper class';

    protected $type = 'VersionMapper';

    protected function getStub(): string
    {
        return __DIR__.'/../../resources/stubs/version-mapper.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\PromptMappers';
    }

    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);
        $stub = str_replace('{{ class }}', class_basename($name), $stub);
        $stub = str_replace('{{ sourceVersion }}', $this->option('from'), $stub);
        $stub = str_replace('{{ targetVersion }}', $this->option('to'), $stub);

        return $stub;
    }
}
