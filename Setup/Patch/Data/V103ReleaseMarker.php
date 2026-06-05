<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.0.3.
 *
 * v1.0.3 restores top-level documentation files (INSTALL.md, USAGE.md,
 * CONFIGURATION.md, COMPATIBILITY.md, UNINSTALL.md) that were
 * accidentally pruned from the publish repo during the v1.0.2 rsync.
 * No code change, no schema change.
 */
class V103ReleaseMarker implements DataPatchInterface
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
        return [V102ReleaseMarker::class];
    }
}
