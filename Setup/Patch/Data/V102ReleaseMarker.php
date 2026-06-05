<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.0.2.
 *
 * v1.0.2 adds admin-configurable Year lower bound + optional Year field
 * + customisable field labels — no schema change, no data migration.
 * This marker satisfies the always-a-patch discipline so setup:upgrade
 * registers patch_list activity for the version bump.
 */
class V102ReleaseMarker implements DataPatchInterface
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
        return [V101ReleaseMarker::class];
    }
}
