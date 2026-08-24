<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Exposes `clicktrail.attribution` and `clicktrail.payload(...)` to templates.
 */
class AttributionExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'clicktrail' => new AttributionVariable(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('clicktrailAttribution', [AttributionVariable::class, 'attribution']),
            new TwigFunction('clicktrailPayload', [AttributionVariable::class, 'payload']),
        ];
    }
}
