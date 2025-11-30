<?php

namespace Yannelli\PromptManager\Facades;

use Illuminate\Support\Facades\Facade;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Pipelines\PromptPipeline;

/**
 * @method static PromptTemplate|null template(string $slug)
 * @method static PromptTemplate templateOrFail(string $slug)
 * @method static RenderResult render(string|PromptTemplate $template, array|PromptContext $context = [])
 * @method static PromptPipeline chain()
 * @method static PromptTemplate create(array|\Yannelli\PromptManager\DTOs\TemplateData $data)
 * @method static PromptTemplate update(string|PromptTemplate $template, array $data)
 * @method static bool delete(string|PromptTemplate $template)
 * @method static void registerType(string $key, string $typeClass)
 * @method static array getTypes()
 * @method static bool hasType(string $key)
 * @method static \Illuminate\Database\Eloquent\Collection all(bool $includeInactive = false)
 * @method static \Illuminate\Database\Eloquent\Collection byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Collection search(string $query)
 * @method static void clearTemplateCache(string $slug)
 * @method static void clearCache()
 *
 * @see \Yannelli\PromptManager\PromptManager
 */
class PromptManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Yannelli\PromptManager\PromptManager::class;
    }
}
