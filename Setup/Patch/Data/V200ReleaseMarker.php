<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * v2.0.0 release marker — module renamed from ETechFlow_VehicleCompat
 * to ETechFlow_ProductFitmentFinder.
 *
 * BREAKING CHANGE — only safe because zero merchant installs existed
 * at rename time. Composer package + PHP namespace + Magento module
 * identifier all renamed in lockstep:
 *
 *   etechflow/module-vehicle-compat  → etechflow/module-product-fitment-finder
 *   ETechFlow\VehicleCompat\         → ETechFlow\ProductFitmentFinder\
 *   ETechFlow_VehicleCompat          → ETechFlow_ProductFitmentFinder
 *
 * Kept intentionally identical (no rename):
 *   - DB table names (etechflow_vehicle_make, etechflow_vehicle_model,
 *     etechflow_vc_garage customer attribute)
 *   - Admin config XML paths (etechflow_vehiclecompat/...)
 *   - Frontend route id (vehiclecompat)
 *   - HMAC license MODULE_ID (vehicle-compat)
 *
 * Rationale: the rename is about positioning (this is a UNIVERSAL fitment
 * finder, not a vehicle-only module), not about migrating existing data.
 * Keeping the internal data identifiers untouched means any future
 * v1.x → v2.0.0 upgrader sees only a cosmetic rename, not a data
 * migration.
 */
class V200ReleaseMarker implements DataPatchInterface
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
        return [V121ReleaseMarker::class];
    }
}
