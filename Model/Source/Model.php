<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\Source;

use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Make\CollectionFactory as MakeCollectionFactory;
use ETechFlow\ProductFitmentFinder\Model\ResourceModel\Model\CollectionFactory as ModelCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Returns all models as a FLAT array with each option carrying its make_id and a
 * "Make – Model" composite label. Frontend / our custom JS uses the make_id field
 * to filter, while the composite label makes the unfiltered fallback usable.
 */
class Model implements OptionSourceInterface
{
    private MakeCollectionFactory  $makeCollectionFactory;
    private ModelCollectionFactory $modelCollectionFactory;
    private ?array $options = null;

    public function __construct(
        MakeCollectionFactory  $makeCollectionFactory,
        ModelCollectionFactory $modelCollectionFactory
    ) {
        $this->makeCollectionFactory  = $makeCollectionFactory;
        $this->modelCollectionFactory = $modelCollectionFactory;
    }

    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $makeNames = [];
        foreach ($this->makeCollectionFactory->create() as $m) {
            $makeNames[(int)$m->getId()] = (string)$m->getData('name');
        }

        $options = [];
        $collection = $this->modelCollectionFactory->create()
            ->setOrder('make_id', 'ASC')
            ->setOrder('sort_order', 'ASC')
            ->setOrder('name', 'ASC');

        foreach ($collection as $model) {
            $makeId    = (int)$model->getData('make_id');
            $makeName  = $makeNames[$makeId] ?? '';
            $modelName = (string)$model->getData('name');
            $options[] = [
                'value'     => (int)$model->getId(),
                'label'     => $modelName,
                'make_id'   => $makeId,
                'make_name' => $makeName,
            ];
        }

        return $this->options = $options;
    }
}
