<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Find;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * /vehiclecompat/find/index — renders the Find Your Parts results page.
 * Uses a 2columns-left layout with custom sidebar + Hyva product cards.
 * URL stays at /car-keys-parts (via FrontControllerForward plugin).
 */
class Index implements HttpGetActionInterface
{
    private PageFactory $pageFactory;

    public function __construct(PageFactory $pageFactory)
    {
        $this->pageFactory = $pageFactory;
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Find Your Parts — Results'));
        return $page;
    }
}
