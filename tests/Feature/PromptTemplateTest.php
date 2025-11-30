<?php

namespace Yannelli\PromptManager\Tests\Feature;

use Yannelli\PromptManager\Tests\TestCase;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Facades\PromptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_template(): void
    {
        $template = PromptManager::create([
            'slug' => 'test-template',
            'name' => 'Test Template',
            'content' => 'Hello, {{ name }}!',
            'type' => 'user',
        ]);

        $this->assertDatabaseHas('prompt_templates', [
            'slug' => 'test-template',
            'name' => 'Test Template',
        ]);

        $this->assertNotNull($template->currentVersion);
        $this->assertEquals('Hello, {{ name }}!', $template->currentVersion->content);
    }

    public function test_can_render_template(): void
    {
        PromptManager::create([
            'slug' => 'greeting',
            'name' => 'Greeting',
            'content' => 'Hello, {{ name }}!',
        ]);

        $result = PromptManager::render('greeting', [
            'variables' => ['name' => 'World'],
        ]);

        $this->assertEquals('Hello, World!', $result->content);
        $this->assertEquals('user', $result->role);
    }

    public function test_can_create_multiple_versions(): void
    {
        $template = PromptManager::create([
            'slug' => 'versioned',
            'name' => 'Versioned Template',
            'content' => 'Version 1',
        ]);

        $template->createVersion('Version 2', ['change_summary' => 'Updated content']);
        $template->createVersion('Version 3', ['change_summary' => 'Another update']);

        $template->refresh(); // Refresh to get updated current_version_id

        $this->assertEquals(3, $template->versions()->count());
        $this->assertEquals('Version 3', $template->currentVersion->content);
    }

    public function test_can_get_specific_version(): void
    {
        $template = PromptManager::create([
            'slug' => 'specific-version',
            'name' => 'Specific Version Test',
            'content' => 'Version 1 content',
        ]);

        $template->createVersion('Version 2 content');

        $result = PromptManager::render('specific-version', [
            'version' => 1,
        ]);

        $this->assertEquals('Version 1 content', $result->content);
    }

    public function test_can_add_components(): void
    {
        $template = PromptManager::create([
            'slug' => 'with-components',
            'name' => 'Template with Components',
            'content' => 'Main content',
            'components' => [
                [
                    'key' => 'header',
                    'name' => 'Header',
                    'content' => 'Header content',
                    'position' => 'prepend',
                ],
                [
                    'key' => 'footer',
                    'name' => 'Footer',
                    'content' => 'Footer content',
                    'position' => 'append',
                ],
            ],
        ]);

        $this->assertEquals(2, $template->components()->count());

        $result = PromptManager::render('with-components');

        $this->assertStringContainsString('Header content', $result->content);
        $this->assertStringContainsString('Main content', $result->content);
        $this->assertStringContainsString('Footer content', $result->content);
    }

    public function test_can_disable_components(): void
    {
        $template = PromptManager::create([
            'slug' => 'toggle-components',
            'name' => 'Toggle Components Test',
            'content' => 'Main content',
            'components' => [
                [
                    'key' => 'optional',
                    'name' => 'Optional',
                    'content' => 'Optional content',
                    'position' => 'append',
                ],
            ],
        ]);

        $result = PromptManager::render('toggle-components', [
            'disabled_components' => ['optional'],
        ]);

        $this->assertStringNotContainsString('Optional content', $result->content);
    }

    public function test_can_search_templates(): void
    {
        PromptManager::create(['slug' => 'hello-world', 'name' => 'Hello World', 'content' => 'Hi']);
        PromptManager::create(['slug' => 'goodbye', 'name' => 'Goodbye', 'content' => 'Bye']);

        $results = PromptManager::search('hello');

        $this->assertCount(1, $results);
        $this->assertEquals('hello-world', $results->first()->slug);
    }
}
