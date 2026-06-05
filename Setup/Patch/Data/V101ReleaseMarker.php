<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.0.1.
 *
 * Continues the always-a-patch discipline established in NDE v1.7.1
 * and across the ETechFlow module suite. Every release ships at least
 * one data patch so `setup:upgrade` always has something to register
 * in `patch_list` — surfacing FS / permissions / DI errors during the
 * patch phase (which retries cleanly) instead of at the end of the
 * upgrade (which doesn't).
 *
 * v1.0.0 had three real data patches (SeedCommonMakes,
 * AddPartsRequiredAttribute, AddVehicleCompatAttribute) which all run
 * once on fresh install. This marker is the v1.0.1 placeholder so
 * existing v1.0.0 installs running setup:upgrade still register
 * patch_list activity even though the v1.0.1 changes are purely
 * route/file/CSS renames (no real data migration to do).
 */
class V101ReleaseMarker implements DataPatchInterface
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
        return [
            SeedCommonMakes::class,
            AddPartsRequiredAttribute::class,
            AddVehicleCompatAttribute::class,
        ];
    }
}
