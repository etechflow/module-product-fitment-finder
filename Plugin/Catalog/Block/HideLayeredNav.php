<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Plugin\Catalog\Block;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\AbstractBlock;

/**
 * When vehicle params are in the URL, suppress the standard layered nav
 * blocks (catalog.leftnav, catalog.search.layered) so they don't try to
 * aggregate facets on our custom Catalog\Product\Collection (which doesn't
 * support Fulltext aggregation methods). The Layer plugin already filters
 * the products; our chips block provides visual feedback in the sidebar.
 */
class HideLayeredNav
{
    private const SUPPRESS_BLOCKS = [
        'catalog.leftnav',
        'catalog.search.leftnav',
        'catalog.search.layered',
        'catalogsearch.leftnav',
    ];

    private RequestInterface $request;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function aroundToHtml(AbstractBlock $subject, callable $proceed)
    {
        if ($this->hasVehicleParams() && in_array($subject->getNameInLayout(), self::SUPPRESS_BLOCKS, true)) {
            return '';
        }
        return $proceed();
    }

    private function hasVehicleParams(): bool
    {
        return ((int)$this->request->getParam('make_id') > 0)
            || ((int)$this->request->getParam('model_id') > 0)
            || ((int)$this->request->getParam('year') > 0)
            || ((int)$this->request->getParam('part_id') > 0);
    }
}
