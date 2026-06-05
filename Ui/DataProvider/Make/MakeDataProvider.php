<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Ui\DataProvider\Make;

use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make\CollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;

class MakeDataProvider extends AbstractDataProvider
{
    private $loadedData = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        foreach ($this->collection->getItems() as $item) {
            $this->loadedData[$item->getId()] = $item->getData();
        }
        return $this->loadedData;
    }

    public function addFilter(Filter $filter)
    {
        $this->getCollection()->addFieldToFilter($filter->getField(), [$filter->getConditionType() => $filter->getValue()]);
        return $this;
    }
}
