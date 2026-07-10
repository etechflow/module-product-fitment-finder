<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Adminhtml\License;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Renders the licence gate page the AdminGate observer redirects to when the
 * module is unlicensed. Guarded by the module's own ACL resource so it never
 * exposes anything a merchant couldn't already reach.
 */
class Gate extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_ProductFitmentFinder::root';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_ProductFitmentFinder::root');
        $resultPage->getConfig()->getTitle()->prepend(__('Product Fitment Finder — Licence'));
        return $resultPage;
    }
}
