<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SeedCommonMakes implements DataPatchInterface
{
    private const MAKES = [
        'Alfa Romeo','Audi','BMW','Chevrolet','Chrysler','Citroen','Dacia','Daewoo','Daihatsu',
        'Fiat','Ford','Honda','Hyundai','Jaguar','Jeep','Kia','Land Rover','Lexus','Mazda',
        'Mercedes-Benz','Mini','Mitsubishi','Nissan','Peugeot','Porsche','Renault','Saab',
        'Seat','Skoda','Smart','Subaru','Suzuki','Tesla','Toyota','Vauxhall','Volkswagen','Volvo',
    ];

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();
        $table = $setup->getTable('etechflow_vehicle_make');

        $existing = $setup->getConnection()->fetchCol("SELECT name FROM $table");
        $existingLower = array_map('strtolower', $existing);

        $rows = [];
        $order = 10;
        foreach (self::MAKES as $name) {
            if (in_array(strtolower($name), $existingLower, true)) {
                continue;
            }
            $rows[] = ['name' => $name, 'sort_order' => $order];
            $order += 10;
        }
        if ($rows) {
            $setup->getConnection()->insertMultiple($table, $rows);
        }
        $setup->endSetup();
        return $this;
    }

    public static function getDependencies(): array { return []; }
    public function getAliases(): array { return []; }
}
