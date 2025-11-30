<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Prompt Type
    |--------------------------------------------------------------------------
    */
    'default_type_key' => 'user',
    'default_type' => \Yannelli\PromptManager\Types\UserPromptType::class,

    /*
    |--------------------------------------------------------------------------
    | Registered Prompt Types
    |--------------------------------------------------------------------------
    */
    'types' => [
        'system' => \Yannelli\PromptManager\Types\SystemPromptType::class,
        'user' => \Yannelli\PromptManager\Types\UserPromptType::class,
        'assistant' => \Yannelli\PromptManager\Types\AssistantPromptType::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Renderer
    |--------------------------------------------------------------------------
    */
    'renderer' => 'simple',

    'renderers' => [
        'simple' => \Yannelli\PromptManager\Renderers\SimpleRenderer::class,
        'blade' => \Yannelli\PromptManager\Renderers\BladeRenderer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Settings
    |--------------------------------------------------------------------------
    */
    'versioning' => [
        'auto_publish' => false,
        'keep_versions' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'templates' => 'prompt_templates',
        'versions' => 'prompt_template_versions',
        'components' => 'prompt_components',
        'executions' => 'prompt_executions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Tracking
    |--------------------------------------------------------------------------
    */
    'track_executions' => false,

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'prompt_manager',
    ],
];
