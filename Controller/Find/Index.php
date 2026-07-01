<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Find;

use ETechFlow\ProductFitmentFinder\Model\Config;
use ETechFlow\ProductFitmentFinder\Model\SeoUrlBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * /vehiclecompat/find/index — renders the Find Your Parts results page.
 *
 * When SEO URLs are on, the finder still POSTs the ugly ?make_id=… query (and
 * old/shared links use it too). This action 301-canonicalises those to the
 * pretty /<prefix>/<make>/<model>/… URL, so customers + Google see the clean
 * URL. FitmentRouter sets '_fitment_seo' when IT forwards a pretty URL here —
 * we skip the redirect then, otherwise we'd loop.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly SeoUrlBuilder $seoUrlBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    public function execute(): ResultInterface
    {
        // Only canonicalise requests that arrived on the plain /vehiclecompat/find
        // route. When FitmentRouter forwards a pretty /<prefix>/… URL here, the
        // path still starts with the SEO prefix — skip it, or we'd loop.
        if (!$this->arrivedViaSeoPath()) {
            $makeId = (int) $this->request->getParam('make_id');
            if ($makeId > 0) {
                $path = $this->seoUrlBuilder->buildPath(
                    $makeId,
                    ((int) $this->request->getParam('model_id')) ?: null,
                    ((int) $this->request->getParam('year')) ?: null,
                    ((int) $this->request->getParam('part_id')) ?: null
                );
                if ($path !== null) {
                    $url = $this->storeManager->getStore()->getBaseUrl() . $path;
                    $extra = $this->carryOverQuery();
                    if ($extra !== '') {
                        $url .= '?' . $extra;
                    }
                    return $this->redirectFactory->create()
                        ->setUrl($url)
                        ->setHttpResponseCode(301);
                }
            }
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Find Your Parts — Results'));
        return $page;
    }

    /**
     * True when this request came in on a pretty /<prefix>/… path (i.e. was
     * forwarded here by FitmentRouter), so it must NOT be redirected again.
     */
    private function arrivedViaSeoPath(): bool
    {
        $prefix = $this->config->getSeoUrlPrefix();
        if ($prefix === '') {
            return false;
        }
        $path = trim((string) $this->request->getPathInfo(), '/');
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    /**
     * Query params to keep on the pretty URL — everything except the vehicle ids
     * (which become path segments). Preserves pagination (p), an OEM term,
     * sort/limit, etc.
     */
    private function carryOverQuery(): string
    {
        $qs = (string) $this->request->getServer('QUERY_STRING');
        if ($qs === '') {
            return '';
        }
        parse_str($qs, $params);
        foreach (['make_id', 'model_id', 'year', 'part_id'] as $drop) {
            unset($params[$drop]);
        }
        return http_build_query($params);
    }
}
