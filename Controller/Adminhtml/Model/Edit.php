<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Adminhtml\Model;

use ETechFlow\ProductFitmentFinder\Model\ModelFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_ProductFitmentFinder::model';

    private PageFactory  $resultPageFactory;
    private ModelFactory $modelFactory;
    private Registry     $registry;

    public function __construct(Context $context, PageFactory $resultPageFactory, ModelFactory $modelFactory, Registry $registry)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->modelFactory      = $modelFactory;
        $this->registry          = $registry;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('model_id');
        $model = $this->modelFactory->create();
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This model no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        $this->registry->register('etechflow_vehicle_model', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_ProductFitmentFinder::model');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Model') : __('New Model'));
        return $resultPage;
    }
}
