<?php

namespace Yannelli\PromptManager\Versioning;

use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Exceptions\VersionNotFoundException;
use Yannelli\PromptManager\Versioning\Strategies\LatestVersionStrategy;
use Yannelli\PromptManager\Versioning\Strategies\SpecificVersionStrategy;
use Yannelli\PromptManager\Versioning\Strategies\MappedVersionStrategy;
use Yannelli\PromptManager\Versioning\Strategies\PublishedVersionStrategy;

class VersionManager implements VersionResolverInterface
{
    /** @var array<string, VersionResolverInterface> */
    protected array $strategies = [];

    protected string $defaultStrategy = 'latest';

    public function __construct()
    {
        // Register built-in strategies
        $this->strategies = [
            'specific' => new SpecificVersionStrategy(),
            'mapped' => new MappedVersionStrategy(),
            'published' => new PublishedVersionStrategy(),
            'latest' => new LatestVersionStrategy(),
        ];
    }

    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion
    {
        // Priority 1: Specific version requested
        if ($context->version !== null) {
            return $this->strategies['specific']->resolve($template, $context);
        }

        // Priority 2: Check for version mapping rules in metadata
        if (!empty($context->metadata['version_mapping'])) {
            $version = $this->strategies['mapped']->resolve($template, $context);
            if ($version) {
                return $version;
            }
        }

        // Priority 3: Check for strategy hint in metadata
        $strategyHint = $context->metadata['version_strategy'] ?? null;
        if ($strategyHint && isset($this->strategies[$strategyHint])) {
            return $this->strategies[$strategyHint]->resolve($template, $context);
        }

        // Priority 4: Use default strategy
        return $this->strategies[$this->defaultStrategy]->resolve($template, $context);
    }

    public function addStrategy(string $name, VersionResolverInterface $strategy): self
    {
        $this->strategies[$name] = $strategy;
        return $this;
    }

    public function removeStrategy(string $name): self
    {
        unset($this->strategies[$name]);
        return $this;
    }

    public function hasStrategy(string $name): bool
    {
        return isset($this->strategies[$name]);
    }

    public function getStrategy(string $name): ?VersionResolverInterface
    {
        return $this->strategies[$name] ?? null;
    }

    public function setDefaultStrategy(string $name): self
    {
        if (!isset($this->strategies[$name])) {
            throw new \InvalidArgumentException("Strategy '{$name}' is not registered.");
        }

        $this->defaultStrategy = $name;
        return $this;
    }

    public function getStrategies(): array
    {
        return $this->strategies;
    }
}
