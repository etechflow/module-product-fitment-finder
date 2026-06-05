<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Ui\DataProvider\Model;

use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Model\CollectionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Ui\DataProvider\AbstractDataProvider;

class FormDataProvider extends AbstractDataProvider
{
    private $loadedData = null;
    private Http $request;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        Http $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->request    = $request;
    }

    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        $id = (int)$this->request->getParam('model_id');
        if ($id) {
            $item = $this->collection->getItemById($id);
            if ($item) {
                $this->loadedData[$id] = $item->getData();
            }
        }
        return $this->loadedData;
    }
}
