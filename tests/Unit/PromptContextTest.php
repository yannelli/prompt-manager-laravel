<?php

namespace Yannelli\PromptManager\Tests\Unit;

use Yannelli\PromptManager\Tests\TestCase;
use Yannelli\PromptManager\DTOs\PromptContext;

class PromptContextTest extends TestCase
{
    public function test_can_create_context_from_array(): void
    {
        $context = PromptContext::make([
            'variables' => ['name' => 'John'],
            'version' => 2,
        ]);

        $this->assertEquals(['name' => 'John'], $context->variables);
        $this->assertEquals(2, $context->version);
    }

    public function test_can_add_variables(): void
    {
        $context = PromptContext::make(['variables' => ['a' => 1]]);
        $newContext = $context->withVariables(['b' => 2]);

        $this->assertEquals(['a' => 1, 'b' => 2], $newContext->variables);
        $this->assertEquals(['a' => 1], $context->variables); // Original unchanged
    }

    public function test_can_enable_components(): void
    {
        $context = PromptContext::make();
        $newContext = $context->enableComponents(['header', 'footer']);

        $this->assertEquals(['header', 'footer'], $newContext->enabledComponents);
    }

    public function test_can_disable_components(): void
    {
        $context = PromptContext::make(['enabled_components' => ['header', 'footer']]);
        $newContext = $context->disableComponents(['header']);

        $this->assertNotContains('header', $newContext->enabledComponents);
        $this->assertContains('header', $newContext->disabledComponents);
    }

    public function test_can_set_previous_result(): void
    {
        $context = PromptContext::make();
        $newContext = $context->withPreviousResult('Previous output');

        $this->assertEquals('Previous output', $newContext->previousResult);
    }

    public function test_converts_to_array(): void
    {
        $context = PromptContext::make([
            'variables' => ['test' => 'value'],
            'version' => 1,
        ]);

        $array = $context->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('variables', $array);
        $this->assertArrayHasKey('version', $array);
    }
}
