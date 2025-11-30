<?php

namespace Yannelli\PromptManager\Versioning\Strategies;

use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Exceptions\VersionNotFoundException;

class PublishedVersionStrategy implements VersionResolverInterface
{
    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion
    {
        // Get the latest published version
        $version = $template->versions()
            ->where('is_published', true)
            ->orderByDesc('version_number')
            ->first();

        if (!$version) {
            // Fallback to current version if no published versions exist
            $version = $template->currentVersion;
        }

        if (!$version) {
            throw VersionNotFoundException::noVersions($template->slug);
        }

        return $version;
    }
}
