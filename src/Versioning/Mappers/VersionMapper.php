<?php

namespace Yannelli\PromptManager\Versioning\Mappers;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;

class VersionMapper
{
    protected array $mappings = [];

    /**
     * Add a mapping rule
     */
    public function addRule(string $templateSlug, string $pattern, int $targetVersion): self
    {
        if (!isset($this->mappings[$templateSlug])) {
            $this->mappings[$templateSlug] = [];
        }

        $this->mappings[$templateSlug][] = [
            'pattern' => $pattern,
            'version' => $targetVersion,
        ];

        return $this;
    }

    /**
     * Get version based on mappings
     */
    public function getVersion(PromptTemplate $template, string $clientVersion): ?PromptTemplateVersion
    {
        $rules = $this->mappings[$template->slug] ?? [];

        foreach ($rules as $rule) {
            if ($this->matches($clientVersion, $rule['pattern'])) {
                return $template->getVersion($rule['version']);
            }
        }

        return null;
    }

    /**
     * Check if client version matches pattern
     */
    protected function matches(string $clientVersion, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        // Semantic versioning patterns
        if (preg_match('/^([<>=!]+)?(\d+)(?:\.(\d+))?(?:\.(\d+))?(.*)$/', $pattern, $matches)) {
            $operator = $matches[1] ?: '=';
            $patternVersion = $matches[2];
            if (isset($matches[3])) $patternVersion .= '.' . $matches[3];
            if (isset($matches[4])) $patternVersion .= '.' . $matches[4];
            if (!empty($matches[5])) $patternVersion .= $matches[5];

            return version_compare($clientVersion, $patternVersion, $operator);
        }

        return $clientVersion === $pattern;
    }

    /**
     * Load mappings from configuration array
     */
    public function loadFromConfig(array $config): self
    {
        foreach ($config as $templateSlug => $rules) {
            foreach ($rules as $rule) {
                $this->addRule(
                    $templateSlug,
                    $rule['pattern'] ?? '*',
                    $rule['version'] ?? 1
                );
            }
        }

        return $this;
    }

    /**
     * Get all mappings
     */
    public function getMappings(): array
    {
        return $this->mappings;
    }

    /**
     * Clear all mappings
     */
    public function clear(): self
    {
        $this->mappings = [];
        return $this;
    }
}
