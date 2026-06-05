<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddVehicleCompatAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'vehicle_compat_data';

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
        $setup    = $this->moduleDataSetup;
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type'                    => 'text',
                'backend'                 => \ETechFlow\ProductFitmentFinder\Model\Attribute\Backend\JsonBackend::class,
                'frontend'                => '',
                'label'                   => 'Vehicle Compatibility Data',
                'input'                   => 'text',
                'class'                   => '',
                'source'                  => '',
                'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => false,                 /* hidden — rendered by UI Modifier */
                'required'                => false,
                'user_defined'            => true,
                'default'                 => null,
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'group'                   => 'General',
                'is_used_in_grid'         => false,
                'is_visible_in_grid'      => false,
                'is_filterable_in_grid'   => false,
            ]
        );

        return $this;
    }

    public static function getDependencies(): array { return []; }
    public function getAliases(): array { return []; }
}
