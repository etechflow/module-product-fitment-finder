<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds the `etechflow_vc_garage` customer attribute (varchar, stores JSON)
 * so logged-in customers' saved vehicles sync across devices.
 *
 * Architecture:
 *   - Guest customers use localStorage only (per-browser).
 *   - Logged-in customers also have their garage mirrored to a customer
 *     attribute. On login, the AJAX sync endpoint merges localStorage
 *     into the customer attribute, then loads the customer attribute
 *     back as the canonical source. So they see "their garage" on every
 *     device they log into.
 *
 * Attribute spec:
 *   - code:    etechflow_vc_garage
 *   - input:   text
 *   - global   YES (same across all stores; can be filtered per-store
 *              client-side because each entry includes makeId which is
 *              store-shared)
 *   - visible: NO (no admin form UI; managed entirely via the storefront
 *              sync endpoint)
 *   - required: NO
 *   - user_defined: YES (it's an extension attribute we own)
 *
 * Idempotent — checks existence before adding. Safe to re-run.
 */
class AddCustomerGarageAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'etechflow_vc_garage';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeSetFactory $attributeSetFactory
    ) {
    }

    public function apply(): self
    {
        $setup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Skip if attribute already exists (idempotent re-runs).
        if ($setup->getAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE)) {
            return $this;
        }

        $setup->addAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE, [
            'label'        => 'Saved Garage (vehicle compatibility)',
            'type'         => 'text',
            'input'        => 'text',
            'required'     => false,
            'visible'      => false,
            'system'       => false,
            'user_defined' => true,
            'position'     => 999,
            'sort_order'   => 999,
            'global'       => 1, // global scope
        ]);

        // Make sure the attribute is in the default attribute set + group.
        $attribute = $setup->getEavConfig()->getAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE);
        if ($attribute && $attribute->getId()) {
            /** @var AttributeSet $attributeSet */
            $attributeSet = $this->attributeSetFactory->create();
            $attributeSetId = $setup->getDefaultAttributeSetId(Customer::ENTITY);
            $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);
            $attribute->addData([
                'attribute_set_id'   => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                // No frontend forms — entirely managed via the sync endpoint.
                'used_in_forms'      => [],
                'is_used_for_customer_segment' => false,
                'is_used_in_grid'    => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
            ]);
            $attribute->save();
        }

        return $this;
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [V111ReleaseMarker::class];
    }
}
