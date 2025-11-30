<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | You may customize the table names used by the package.
    |
    */
    'tables' => [
        'prompt_templates' => 'prompt_templates',
        'prompt_template_versions' => 'prompt_template_versions',
        'prompt_components' => 'prompt_components',
        'prompt_template_components' => 'prompt_template_components',
        'prompt_types' => 'prompt_types',
        'prompt_pipelines' => 'prompt_pipelines',
        'prompt_pipeline_steps' => 'prompt_pipeline_steps',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | You may use your own models by changing the values below.
    |
    */
    'models' => [
        'prompt_template' => \PromptManager\PromptTemplates\Models\PromptTemplate::class,
        'prompt_template_version' => \PromptManager\PromptTemplates\Models\PromptTemplateVersion::class,
        'prompt_component' => \PromptManager\PromptTemplates\Models\PromptComponent::class,
        'prompt_type' => \PromptManager\PromptTemplates\Models\PromptType::class,
        'prompt_pipeline' => \PromptManager\PromptTemplates\Models\PromptPipeline::class,
        'prompt_pipeline_step' => \PromptManager\PromptTemplates\Models\PromptPipelineStep::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Type Handlers
    |--------------------------------------------------------------------------
    |
    | Register your prompt type handlers here. The key is the type identifier
    | and the value is the handler class that will process templates of that type.
    |
    */
    'type_handlers' => [
        'default' => \PromptManager\PromptTemplates\Handlers\DefaultTypeHandler::class,
        'chat' => \PromptManager\PromptTemplates\Handlers\ChatTypeHandler::class,
        'completion' => \PromptManager\PromptTemplates\Handlers\CompletionTypeHandler::class,
        'instruction' => \PromptManager\PromptTemplates\Handlers\InstructionTypeHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Mappers
    |--------------------------------------------------------------------------
    |
    | Register custom version mappers for handling backward compatibility
    | when migrating between prompt template versions.
    |
    */
    'version_mappers' => [
        // 'v1_to_v2' => \App\PromptMappers\V1ToV2Mapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Variable Delimiters
    |--------------------------------------------------------------------------
    |
    | The delimiters used for template variables.
    |
    */
    'variable_delimiters' => [
        'start' => '{{',
        'end' => '}}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for rendered prompts.
    |
    */
    'cache' => [
        'enabled' => env('PROMPT_TEMPLATES_CACHE_ENABLED', true),
        'ttl' => env('PROMPT_TEMPLATES_CACHE_TTL', 3600),
        'prefix' => 'prompt_templates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline Settings
    |--------------------------------------------------------------------------
    |
    | Configure the prompt pipeline behavior.
    |
    */
    'pipeline' => [
        'max_depth' => 10, // Maximum nesting depth for piped templates
        'timeout' => 30, // Timeout in seconds for pipeline execution
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Settings
    |--------------------------------------------------------------------------
    |
    | Configure default component behavior.
    |
    */
    'components' => [
        'default_enabled' => true,
        'allow_user_override' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model for ownership relations.
    |
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | Enable soft deletes for prompt templates.
    |
    */
    'soft_deletes' => true,
];
