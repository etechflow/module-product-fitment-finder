<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\Source;

use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Make implements OptionSourceInterface
{
    private CollectionFactory $collectionFactory;
    private ?array $options = null;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        $options = [['value' => '', 'label' => __('-- Select Make --')]];
        $collection = $this->collectionFactory->create()
            ->setOrder('sort_order', 'ASC')
            ->setOrder('name', 'ASC');
        foreach ($collection as $make) {
            $options[] = [
                'value' => (int)$make->getId(),
                'label' => $make->getData('name'),
            ];
        }
        return $this->options = $options;
    }
}
