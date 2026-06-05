<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.2.0.
 *
 * v1.2.0 closes the last two Amasty-parity gaps:
 *
 *   1. **Customer-attribute garage sync** — logged-in customers' saved
 *      vehicles persist to a customer EAV attribute (etechflow_vc_garage)
 *      so the garage syncs across devices. Implemented via
 *      AddCustomerGarageAttribute data patch + Controller/Garage/Sync.
 *
 *   2. **OEM / part-number search** — admin-opt-in search input on the
 *      Find page that LIKE-matches the customer's term against configured
 *      product attribute codes (default `sku`, can extend to mpn,
 *      manufacturer_part_number, custom_oem, etc.). All catalog filter
 *      logic lives in FindResults block.
 *
 * Marker depends on AddCustomerGarageAttribute so the customer attribute
 * is in place before this release is considered "applied".
 */
class V120ReleaseMarker implements DataPatchInterface
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
        return [AddCustomerGarageAttribute::class];
    }
}
