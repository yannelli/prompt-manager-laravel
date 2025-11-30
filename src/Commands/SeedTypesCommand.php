<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedTypesCommand extends Command
{
    protected $signature = 'prompt-templates:seed-types
                            {--force : Force overwrite existing types}';

    protected $description = 'Seed the default prompt types';

    public function handle(): int
    {
        $this->info('Seeding default prompt types...');

        $typeClass = config('prompt-templates.models.prompt_type');
        $handlers = config('prompt-templates.type_handlers', []);

        $defaultTypes = [
            [
                'name' => 'Default',
                'slug' => 'default',
                'description' => 'Default prompt type with basic variable substitution',
                'handler_class' => $handlers['default'] ?? \PromptManager\PromptTemplates\Handlers\DefaultTypeHandler::class,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Chat',
                'slug' => 'chat',
                'description' => 'Chat-style prompts for conversational AI (OpenAI Chat, Claude, etc.)',
                'handler_class' => $handlers['chat'] ?? \PromptManager\PromptTemplates\Handlers\ChatTypeHandler::class,
                'is_system' => true,
                'is_active' => true,
                'schema' => [
                    'system_prompt' => ['type' => 'string', 'required' => false],
                    'user_prompt' => ['type' => 'string', 'required' => true],
                    'assistant_prompt' => ['type' => 'string', 'required' => false],
                ],
            ],
            [
                'name' => 'Completion',
                'slug' => 'completion',
                'description' => 'Completion-style prompts for text completion models',
                'handler_class' => $handlers['completion'] ?? \PromptManager\PromptTemplates\Handlers\CompletionTypeHandler::class,
                'is_system' => true,
                'is_active' => true,
                'schema' => [
                    'content' => ['type' => 'string', 'required' => true],
                ],
            ],
            [
                'name' => 'Instruction',
                'slug' => 'instruction',
                'description' => 'Instruction-following format (Alpaca, Vicuna style)',
                'handler_class' => $handlers['instruction'] ?? \PromptManager\PromptTemplates\Handlers\InstructionTypeHandler::class,
                'is_system' => true,
                'is_active' => true,
                'config' => [
                    'instruction_prefix' => "### Instruction:\n",
                    'input_prefix' => "### Input:\n",
                    'response_prefix' => "### Response:\n",
                ],
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($defaultTypes as $type) {
            $existing = $typeClass::where('slug', $type['slug'])->first();

            if ($existing && ! $this->option('force')) {
                $this->line("  ⊘ Skipped: {$type['name']} (already exists)");
                $skipped++;

                continue;
            }

            if ($existing) {
                $existing->update($type);
                $this->line("  ↻ Updated: {$type['name']}");
            } else {
                $typeClass::create($type);
                $this->line("  ✓ Created: {$type['name']}");
            }
            $created++;
        }

        $this->newLine();
        $this->info("Done! Created/updated: {$created}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
