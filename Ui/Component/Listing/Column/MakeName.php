<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Ui\Component\Listing\Column;

use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make\CollectionFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Resolves make_id → make name for the models grid.
 */
class MakeName extends Column
{
    private CollectionFactory $collectionFactory;
    private ?array $cache = null;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        CollectionFactory $collectionFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->collectionFactory = $collectionFactory;
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getName();
        foreach ($dataSource['data']['items'] as &$item) {
            $makeId = (int)($item['make_id'] ?? 0);
            $item[$name] = $this->resolveMakeName($makeId);
        }
        return $dataSource;
    }

    private function resolveMakeName(int $id): string
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->collectionFactory->create() as $m) {
                $this->cache[(int)$m->getId()] = (string)$m->getData('name');
            }
        }
        return $this->cache[$id] ?? '';
    }
}
