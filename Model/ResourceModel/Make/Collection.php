<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'make_id';
    protected $_eventPrefix = 'etechflow_vehicle_make_collection';
    protected $_eventObject = 'make_collection';

    protected function _construct()
    {
        $this->_init(
            \ETechFlow\ProductFitmentFinder\Model\Make::class,
            \ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make::class
        );
    }
}
