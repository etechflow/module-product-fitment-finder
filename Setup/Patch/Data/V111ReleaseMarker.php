<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.1.1.
 *
 * v1.1.1 makes the surrounding chrome (button text, page title, empty
 * states, garage prompts) admin-configurable so the Part Finder truly
 * works for non-vehicle merchants. v1.0.2 made the dropdown LABELS
 * configurable but the BUTTON ("FIND PARTS") and page TITLE ("Find
 * Your Parts") were still hardcoded automotive copy, leaking the
 * vehicle vibe into phone-case / watch-strap / appliance stores.
 *
 * Also closes the v1.1.0 customer garage UX gaps: a Save Selection
 * button on the Part Finder + an empty-state Garage placeholder so
 * customers can actually discover the feature.
 *
 * No schema change.
 */
class V111ReleaseMarker implements DataPatchInterface
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
        return [V110ReleaseMarker::class];
    }
}
