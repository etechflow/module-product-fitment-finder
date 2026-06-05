<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds the `parts_required` multiselect product attribute used by the
 * Find-Your-Parts cascading lookup (frontend modal) and the bulk CSV
 * importer. Options are seeded later by the importer (one per distinct
 * "Parts Required" value in the source CSV).
 */
class AddPartsRequiredAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'parts_required';

    private ModuleDataSetupInterface $moduleDataSetup;
    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        if ($eavSetup->getAttribute(Product::ENTITY, self::ATTRIBUTE_CODE)) {
            return $this;
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type'                    => 'varchar',
                'backend'                 => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                'frontend'                => '',
                'label'                   => 'Parts Required',
                'input'                   => 'multiselect',
                'class'                   => '',
                'source'                  => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
                'global'                  => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => true,
                'default'                 => null,
                'searchable'              => true,
                'filterable'              => true,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => true,
                'unique'                  => false,
                'group'                   => 'General',
                'is_used_in_grid'         => true,
                'is_visible_in_grid'      => true,
                'is_filterable_in_grid'   => true,
            ]
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            \ETechFlow\ProductFitmentFinder\Setup\Patch\Data\AddVehicleCompatAttribute::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
