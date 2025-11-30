<?php

namespace Yannelli\PromptManager\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Yannelli\PromptManager\PromptManagerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PromptManagerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite in-memory database for testing
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up package config
        config()->set('prompt-manager.track_executions', true);
        config()->set('prompt-manager.cache.enabled', false);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('user');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['slug', 'is_active']);
            $table->index('type');
        });

        Schema::create('prompt_template_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prompt_template_id')
                  ->constrained('prompt_templates')
                  ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('content');
            $table->json('variables')->nullable();
            $table->json('component_config')->nullable();
            $table->json('mapping_rules')->nullable();
            $table->string('change_summary')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['prompt_template_id', 'version_number']);
            $table->index('is_published');
        });

        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->foreign('current_version_id')
                  ->references('id')
                  ->on('prompt_template_versions')
                  ->nullOnDelete();
        });

        Schema::create('prompt_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prompt_template_id')
                  ->constrained('prompt_templates')
                  ->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('content');
            $table->string('position')->default('append');
            $table->integer('order')->default(0);
            $table->boolean('is_default_enabled')->default(true);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->unique(['prompt_template_id', 'key']);
            $table->index(['prompt_template_id', 'order']);
        });

        Schema::create('prompt_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('prompt_template_version_id')
                  ->constrained('prompt_template_versions')
                  ->cascadeOnDelete();
            $table->json('input_variables');
            $table->json('enabled_components')->nullable();
            $table->text('rendered_output');
            $table->json('pipeline_chain')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }
}
