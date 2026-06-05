<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Plugin\Catalog\Block;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Plugin kept for reference / future use. The current strategy is to forward
 * /car-keys-parts?make_id=... requests to the dedicated find controller via
 * ETechFlow\ProductFitmentFinder\Plugin\App\FrontControllerForward (registered in di.xml).
 * This plugin is a no-op so nothing breaks if both are registered.
 */
class ListProductFilter
{
    public function afterGetLoadedProductCollection(ListProduct $subject, $original)
    {
        return $original;
    }
}
