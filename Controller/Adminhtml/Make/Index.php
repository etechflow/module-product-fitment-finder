<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Adminhtml\Make;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_ProductFitmentFinder::make';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_ProductFitmentFinder::make');
        $resultPage->getConfig()->getTitle()->prepend(__('Vehicle Makes'));
        return $resultPage;
    }
}
