<?php

namespace Yannelli\PromptManager\Tests\Feature;

use Yannelli\PromptManager\Tests\TestCase;
use Yannelli\PromptManager\Facades\PromptManager;
use Yannelli\PromptManager\DTOs\PromptContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PromptPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PromptManager::create([
            'slug' => 'system-prompt',
            'name' => 'System',
            'type' => 'system',
            'content' => 'You are a helpful assistant.',
        ]);

        PromptManager::create([
            'slug' => 'user-prompt',
            'name' => 'User',
            'type' => 'user',
            'content' => 'User says: {{ message }}',
        ]);

        PromptManager::create([
            'slug' => 'format-prompt',
            'name' => 'Format',
            'type' => 'user',
            'content' => 'Previous: {{ previous_result }} - Format as JSON',
        ]);
    }

    public function test_can_chain_templates(): void
    {
        $result = PromptManager::chain()
            ->template('system-prompt')
            ->template('user-prompt', ['message' => 'Hello!'])
            ->run(PromptContext::make());

        $this->assertStringContainsString('Hello!', $result->content);
    }

    public function test_can_get_all_results(): void
    {
        $results = PromptManager::chain()
            ->template('system-prompt')
            ->template('user-prompt', ['message' => 'Test'])
            ->run(PromptContext::make(), collectAll: true);

        $this->assertCount(2, $results);
        $this->assertEquals('system', $results[0]->role);
        $this->assertEquals('user', $results[1]->role);
    }

    public function test_can_convert_to_messages(): void
    {
        $messages = PromptManager::chain()
            ->template('system-prompt')
            ->template('user-prompt', ['message' => 'Hi'])
            ->toMessages(PromptContext::make());

        $this->assertCount(2, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('user', $messages[1]['role']);
    }

    public function test_passes_previous_result(): void
    {
        $result = PromptManager::chain()
            ->template('user-prompt', ['message' => 'Initial'])
            ->template('format-prompt')
            ->run(PromptContext::make());

        $this->assertStringContainsString('Previous:', $result->content);
        $this->assertStringContainsString('Initial', $result->content);
    }

    public function test_can_concatenate_results(): void
    {
        $output = PromptManager::chain()
            ->template('system-prompt')
            ->template('user-prompt', ['message' => 'Test'])
            ->toString(PromptContext::make());

        $this->assertIsString($output);
        $this->assertStringContainsString('helpful assistant', $output);
        $this->assertStringContainsString('Test', $output);
    }
}
