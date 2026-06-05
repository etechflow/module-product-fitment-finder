<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.2.1.
 *
 * v1.2.1 is the final polish pass on customer-facing copy + adds three
 * optional customer-facing tooltips + replaces hardcoded inline colours
 * with a CSS custom property so themes can override.
 *
 * No schema change.
 */
class V121ReleaseMarker implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        return $this;
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [V120ReleaseMarker::class];
    }
}
