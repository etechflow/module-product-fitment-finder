<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model;

use ETechFlow\ProductFitmentFinder\Api\Data\MakeInterface;
use Magento\Framework\Model\AbstractModel;

class Make extends AbstractModel implements MakeInterface
{
    protected $_eventPrefix = 'etechflow_vehicle_make';

    protected function _construct()
    {
        $this->_init(\ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make::class);
    }

    public function getMakeId()              { return $this->getData(self::MAKE_ID); }
    public function setMakeId($id)           { return $this->setData(self::MAKE_ID, $id); }
    public function getName(): ?string       { return $this->getData(self::NAME); }
    public function setName(string $name)    { return $this->setData(self::NAME, $name); }
    public function getSortOrder(): int      { return (int)$this->getData(self::SORT_ORDER); }
    public function setSortOrder(int $order) { return $this->setData(self::SORT_ORDER, $order); }
}
