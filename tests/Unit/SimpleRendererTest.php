<?php

namespace Yannelli\PromptManager\Tests\Unit;

use Yannelli\PromptManager\Tests\TestCase;
use Yannelli\PromptManager\Renderers\SimpleRenderer;

class SimpleRendererTest extends TestCase
{
    protected SimpleRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new SimpleRenderer();
    }

    public function test_renders_simple_variables(): void
    {
        $template = 'Hello, {{ name }}!';
        $result = $this->renderer->render($template, ['name' => 'World']);

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_renders_multiple_variables(): void
    {
        $template = '{{ greeting }}, {{ name }}!';
        $result = $this->renderer->render($template, [
            'greeting' => 'Hello',
            'name' => 'World',
        ]);

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_handles_missing_variables(): void
    {
        $template = 'Hello, {{ name }}!';
        $result = $this->renderer->render($template, []);

        $this->assertEquals('Hello, {{ name }}!', $result);
    }

    public function test_handles_nested_arrays(): void
    {
        $template = 'Name: {{ user.name }}, Age: {{ user.age }}';
        $result = $this->renderer->render($template, [
            'user' => ['name' => 'John', 'age' => 30],
        ]);

        $this->assertEquals('Name: John, Age: 30', $result);
    }

    public function test_supports_all_templates(): void
    {
        $this->assertTrue($this->renderer->supports('any template'));
    }

    public function test_can_use_custom_delimiters(): void
    {
        $renderer = new SimpleRenderer('<%', '%>');
        $template = 'Hello, <% name %>!';
        $result = $renderer->render($template, ['name' => 'World']);

        $this->assertEquals('Hello, World!', $result);
    }
}
