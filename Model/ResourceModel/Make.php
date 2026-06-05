<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Make extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('etechflow_vehicle_make', 'make_id');
    }
}
