<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class MakeActions extends Column
{
    private UrlInterface $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (isset($item['make_id'])) {
                $item[$name] = [
                    'edit' => [
                        'href' => $this->urlBuilder->getUrl('etechflow_vehicle/make/edit', ['make_id' => $item['make_id']]),
                        'label' => __('Edit'),
                    ],
                    'delete' => [
                        'href' => $this->urlBuilder->getUrl('etechflow_vehicle/make/delete', ['make_id' => $item['make_id']]),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete this make?'),
                            'message' => __('All models under this make will also be deleted. Are you sure?'),
                        ],
                    ],
                ];
            }
        }
        return $dataSource;
    }
}
