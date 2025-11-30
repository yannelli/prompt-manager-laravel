<?php

namespace Yannelli\PromptManager\Versioning\Strategies;

use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Exceptions\VersionNotFoundException;

class MappedVersionStrategy implements VersionResolverInterface
{
    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion
    {
        $mapping = $context->metadata['version_mapping'] ?? [];

        if (empty($mapping)) {
            throw new \InvalidArgumentException('Mapped version strategy requires version_mapping in metadata.');
        }

        // Extract mapping configuration
        $rules = $mapping['rules'] ?? [];
        $compareField = $mapping['field'] ?? 'client_version';
        $compareValue = $mapping[$compareField] ?? $context->variables[$compareField] ?? null;

        // Evaluate rules in order (first match wins)
        foreach ($rules as $rule) {
            $rulePattern = $rule[$compareField] ?? $rule['pattern'] ?? '*';
            $targetVersion = $rule['template_version'] ?? $rule['version'] ?? null;

            if ($targetVersion === null) {
                continue;
            }

            if ($this->matchesRule($compareValue, $rulePattern)) {
                $version = $template->getVersion($targetVersion);
                if ($version) {
                    return $version;
                }
            }
        }

        // Fallback to latest if no rule matches
        return (new LatestVersionStrategy())->resolve($template, $context);
    }

    protected function matchesRule(?string $value, string $pattern): bool
    {
        // Wildcard matches everything
        if ($pattern === '*') {
            return true;
        }

        // Null value only matches wildcard
        if ($value === null) {
            return false;
        }

        // Version comparison operators
        if (preg_match('/^(>=|<=|>|<|=|!=)(.+)$/', $pattern, $matches)) {
            $operator = $matches[1];
            $compareVersion = trim($matches[2]);

            return match ($operator) {
                '>=' => version_compare($value, $compareVersion, '>='),
                '<=' => version_compare($value, $compareVersion, '<='),
                '>' => version_compare($value, $compareVersion, '>'),
                '<' => version_compare($value, $compareVersion, '<'),
                '=' => version_compare($value, $compareVersion, '='),
                '!=' => version_compare($value, $compareVersion, '!='),
                default => false,
            };
        }

        // Range pattern (e.g., "1.0-2.0")
        if (str_contains($pattern, '-') && !str_starts_with($pattern, '-')) {
            $parts = explode('-', $pattern, 2);
            if (count($parts) === 2) {
                $min = trim($parts[0]);
                $max = trim($parts[1]);
                return version_compare($value, $min, '>=') && version_compare($value, $max, '<=');
            }
        }

        // Exact match
        return $value === $pattern;
    }
}
