<?php
declare(strict_types=1);
namespace ETechFlow\ProductFitmentFinder\Controller\Tree;

use ETechFlow\ProductFitmentFinder\Block\PartFinderData;
use ETechFlow\ProductFitmentFinder\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\LayoutInterface;

/**
 * /vehiclecompat/tree.json — serves the vehicle make/model/parts tree as a standalone,
 * browser-cacheable JSON resource so it can be lazy-loaded by the header
 * Find-Your-Parts widget on first interaction instead of bloating every page
 * with 243 KB of inline JSON.
 *
 * Cache: 1 hour browser cache, validated by underlying block cache
 * (etechflow_vehicle_compat_tree_v2) which is tagged with
 * CatalogProduct::CACHE_TAG so any product save invalidates it.
 */
class Index implements HttpGetActionInterface
{
    private RawFactory $rawFactory;
    private LayoutInterface $layout;
    private Config $config;

    public function __construct(
        RawFactory $rawFactory,
        LayoutInterface $layout,
        Config $config
    ) {
        $this->rawFactory = $rawFactory;
        $this->layout = $layout;
        $this->config = $config;
    }

    public function execute(): ResponseInterface|Raw
    {
        // Licence gate: no vehicle tree for an unlicensed store.
        if (!$this->config->isEnabled()) {
            $blocked = $this->rawFactory->create();
            $blocked->setHttpResponseCode(404);
            return $blocked;
        }

        /** @var PartFinderData $block */
        $block = $this->layout->createBlock(PartFinderData::class);
        $json = $block->getTreeJson();

        $result = $this->rawFactory->create();
        $result->setContents($json);
        $result->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        // Browser cache for 1 hour; CDN/proxy may extend.
        $result->setHeader('Cache-Control', 'public, max-age=3600, s-maxage=3600', true);
        return $result;
    }
}
