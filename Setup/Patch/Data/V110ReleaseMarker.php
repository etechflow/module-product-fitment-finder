<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.1.0 — the Amasty-competitor feature set.
 *
 * v1.1.0 adds:
 *   - PDP fitment badge (Block/Product/FitmentBadge + template + layout)
 *   - SEO-friendly URLs (custom Router)
 *   - Customer "My Garage" widget (localStorage-based MVP)
 *   - Universal positioning (composer.json description rewrite)
 *
 * All four features opt-in via admin config. Defaults preserve v1.0.x
 * behaviour exactly — existing installs see no change unless they
 * intentionally enable a feature in Stores → Configuration.
 *
 * No schema change, no data migration. This marker satisfies the
 * always-a-patch discipline.
 */
class V110ReleaseMarker implements DataPatchInterface
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
        return [V103ReleaseMarker::class];
    }
}
