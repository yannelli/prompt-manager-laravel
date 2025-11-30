<?php

namespace Yannelli\PromptManager\Versioning\Strategies;

use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Exceptions\VersionNotFoundException;

class LatestVersionStrategy implements VersionResolverInterface
{
    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion
    {
        // First try to get the current version set on the template
        $version = $template->currentVersion;

        // If no current version, get the latest by version number
        if (!$version) {
            $version = $template->versions()
                ->orderByDesc('version_number')
                ->first();
        }

        if (!$version) {
            throw VersionNotFoundException::noVersions($template->slug);
        }

        return $version;
    }
}
