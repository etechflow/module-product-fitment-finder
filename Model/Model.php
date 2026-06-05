<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model;

use ETechFlow\ProductFitmentFinder\Api\Data\ModelInterface;
use Magento\Framework\Model\AbstractModel;

class Model extends AbstractModel implements ModelInterface
{
    protected $_eventPrefix = 'etechflow_vehicle_model';

    protected function _construct()
    {
        $this->_init(\ETechFlow\ProductFitmentFinder\Model\ResourceModel\Model::class);
    }

    public function getModelId()              { return $this->getData(self::MODEL_ID); }
    public function setModelId($id)           { return $this->setData(self::MODEL_ID, $id); }
    public function getMakeId(): ?int         { $v = $this->getData(self::MAKE_ID); return $v === null ? null : (int)$v; }
    public function setMakeId(int $makeId)    { return $this->setData(self::MAKE_ID, $makeId); }
    public function getName(): ?string        { return $this->getData(self::NAME); }
    public function setName(string $name)     { return $this->setData(self::NAME, $name); }
    public function getSortOrder(): int       { return (int)$this->getData(self::SORT_ORDER); }
    public function setSortOrder(int $order)  { return $this->setData(self::SORT_ORDER, $order); }
}
