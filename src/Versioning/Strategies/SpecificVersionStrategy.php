<?php

namespace Yannelli\PromptManager\Versioning\Strategies;

use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Exceptions\VersionNotFoundException;

class SpecificVersionStrategy implements VersionResolverInterface
{
    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion
    {
        $versionNumber = $context->version;

        if ($versionNumber === null) {
            throw new \InvalidArgumentException('Specific version strategy requires a version number in context.');
        }

        $version = $template->versions()
            ->where('version_number', $versionNumber)
            ->first();

        if (!$version) {
            throw VersionNotFoundException::forTemplate($template->slug, $versionNumber);
        }

        return $version;
    }
}
