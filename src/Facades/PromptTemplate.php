<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Facades;

use Illuminate\Support\Facades\Facade;
use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate as PromptTemplateModel;
use PromptManager\PromptTemplates\Renderers\PromptRenderer;

/**
 * @method static RenderedPrompt render(PromptTemplateModel|\PromptManager\PromptTemplates\Models\PromptTemplateVersion $renderable, array $variables = [], array $options = [])
 * @method static void clearCache(PromptTemplateModel|\PromptManager\PromptTemplates\Models\PromptTemplateVersion $renderable)
 *
 * @see PromptRenderer
 */
class PromptTemplate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'prompt-templates.renderer';
    }

    /**
     * Find a template by slug and render it.
     */
    public static function renderBySlug(string $slug, array $variables = [], array $options = []): ?RenderedPrompt
    {
        $templateClass = config('prompt-templates.models.prompt_template', PromptTemplateModel::class);
        $template = $templateClass::findBySlug($slug);

        if (! $template) {
            return null;
        }

        return static::render($template, $variables, $options);
    }

    /**
     * Find a template by UUID and render it.
     */
    public static function renderByUuid(string $uuid, array $variables = [], array $options = []): ?RenderedPrompt
    {
        $templateClass = config('prompt-templates.models.prompt_template', PromptTemplateModel::class);
        $template = $templateClass::findByUuid($uuid);

        if (! $template) {
            return null;
        }

        return static::render($template, $variables, $options);
    }

    /**
     * Create a new template.
     */
    public static function create(array $attributes): PromptTemplateModel
    {
        $templateClass = config('prompt-templates.models.prompt_template', PromptTemplateModel::class);

        return $templateClass::create($attributes);
    }

    /**
     * Find a template by slug.
     */
    public static function findBySlug(string $slug): ?PromptTemplateModel
    {
        $templateClass = config('prompt-templates.models.prompt_template', PromptTemplateModel::class);

        return $templateClass::findBySlug($slug);
    }

    /**
     * Find a template by UUID.
     */
    public static function findByUuid(string $uuid): ?PromptTemplateModel
    {
        $templateClass = config('prompt-templates.models.prompt_template', PromptTemplateModel::class);

        return $templateClass::findByUuid($uuid);
    }
}
