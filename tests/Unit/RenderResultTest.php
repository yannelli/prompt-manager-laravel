<?php

namespace Yannelli\PromptManager\Tests\Unit;

use Yannelli\PromptManager\Tests\TestCase;
use Yannelli\PromptManager\DTOs\RenderResult;

class RenderResultTest extends TestCase
{
    public function test_can_create_result(): void
    {
        $result = RenderResult::make('Hello, World!', 'user');

        $this->assertEquals('Hello, World!', $result->content);
        $this->assertEquals('user', $result->role);
    }

    public function test_can_create_with_options(): void
    {
        $result = RenderResult::make('Content', 'system', [
            'template_slug' => 'test-template',
            'version_number' => 2,
            'used_components' => ['header'],
        ]);

        $this->assertEquals('test-template', $result->templateSlug);
        $this->assertEquals(2, $result->versionNumber);
        $this->assertEquals(['header'], $result->usedComponents);
    }

    public function test_can_convert_to_message(): void
    {
        $result = RenderResult::make('Hello!', 'user');
        $message = $result->toMessage();

        $this->assertEquals([
            'role' => 'user',
            'content' => 'Hello!',
        ], $message);
    }

    public function test_can_add_metadata(): void
    {
        $result = RenderResult::make('Content', 'user');
        $newResult = $result->withMetadata(['key' => 'value']);

        $this->assertEquals(['key' => 'value'], $newResult->metadata);
    }

    public function test_is_json_serializable(): void
    {
        $result = RenderResult::make('Content', 'user');
        $json = json_encode($result);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('Content', $decoded['content']);
    }
}
