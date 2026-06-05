<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Adminhtml\Make;

use ETechFlow\ProductFitmentFinder\Model\MakeFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_ProductFitmentFinder::make';

    private PageFactory $resultPageFactory;
    private MakeFactory $makeFactory;
    private Registry    $registry;

    public function __construct(Context $context, PageFactory $resultPageFactory, MakeFactory $makeFactory, Registry $registry)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->makeFactory       = $makeFactory;
        $this->registry          = $registry;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('make_id');
        $model = $this->makeFactory->create();
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This make no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        $this->registry->register('etechflow_vehicle_make', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_ProductFitmentFinder::make');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Make') : __('New Make'));
        return $resultPage;
    }
}
