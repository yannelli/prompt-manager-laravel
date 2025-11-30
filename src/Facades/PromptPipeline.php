<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Facades;

use Illuminate\Support\Facades\Facade;
use PromptManager\PromptTemplates\DTOs\PipelineContext;
use PromptManager\PromptTemplates\Models\PromptPipeline as PromptPipelineModel;
use PromptManager\PromptTemplates\Pipelines\PipelineBuilder;
use PromptManager\PromptTemplates\Pipelines\PipelineExecutor;

/**
 * @method static PipelineContext execute(PromptPipelineModel $pipeline, array $input = [], array $options = [])
 * @method static PipelineContext chain(array $pipelines, array $input = [])
 * @method static array parallel(array $pipelines, array $input = [])
 * @method static PipelineContext fromTemplates(array $templates, array $input = [])
 * @method static int getCurrentDepth()
 * @method static int getMaxDepth()
 * @method static PipelineExecutor setMaxDepth(int $maxDepth)
 *
 * @see PipelineExecutor
 */
class PromptPipeline extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'prompt-templates.pipeline';
    }

    /**
     * Create a new pipeline builder.
     */
    public static function build(?string $name = null): PipelineBuilder
    {
        return PipelineBuilder::create($name);
    }

    /**
     * Execute a pipeline by slug.
     */
    public static function executeBySlug(string $slug, array $input = [], array $options = []): ?PipelineContext
    {
        $pipelineClass = config('prompt-templates.models.prompt_pipeline', PromptPipelineModel::class);
        $pipeline = $pipelineClass::findBySlug($slug);

        if (! $pipeline) {
            return null;
        }

        return static::execute($pipeline, $input, $options);
    }

    /**
     * Find a pipeline by slug.
     */
    public static function findBySlug(string $slug): ?PromptPipelineModel
    {
        $pipelineClass = config('prompt-templates.models.prompt_pipeline', PromptPipelineModel::class);

        return $pipelineClass::findBySlug($slug);
    }

    /**
     * Create a pipeline from an array of templates.
     */
    public static function createFromTemplates(string $name, array $templates, array $config = []): PromptPipelineModel
    {
        $builder = static::build($name)->config($config);

        foreach ($templates as $index => $template) {
            $builder->addTemplate(
                $template,
                is_string($index) ? $index : null
            );
        }

        return $builder->save();
    }
}
