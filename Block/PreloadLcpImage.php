<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Block;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Emits a <link rel="preload" as="image"> for the first product in the
 * filtered listing. This lets the browser kick off the LCP image download
 * immediately while HTML is still parsing, instead of waiting for the
 * product grid to render. Self-disables unless vehicle filter params are
 * present in the URL.
 */
class PreloadLcpImage extends Template
{
    private RequestInterface $request;
    private Resolver $layerResolver;
    private ImageHelper $imageHelper;

    public function __construct(
        Context $context,
        RequestInterface $request,
        Resolver $layerResolver,
        ImageHelper $imageHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->request       = $request;
        $this->layerResolver = $layerResolver;
        $this->imageHelper   = $imageHelper;
    }

    private function hasAnyFilter(): bool
    {
        return ((int)$this->request->getParam('make_id') > 0)
            || ((int)$this->request->getParam('model_id') > 0)
            || ((int)$this->request->getParam('year') > 0)
            || ((int)$this->request->getParam('part_id') > 0);
    }

    public function getLcpImageUrl(): string
    {
        try {
            $layer = $this->layerResolver->get();
            $collection = $layer->getProductCollection();
            $collection->setPageSize(1)->setCurPage(1);
            $product = $collection->getFirstItem();
            if (!$product || !$product->getId()) {
                return '';
            }
            return (string) $this->imageHelper
                ->init($product, 'category_page_grid')
                ->getUrl();
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function _toHtml()
    {
        if (!$this->hasAnyFilter()) {
            return '';
        }
        return parent::_toHtml();
    }
}
