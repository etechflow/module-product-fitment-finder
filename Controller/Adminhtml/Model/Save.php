<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Adminhtml\Model;

use ETechFlow\ProductFitmentFinder\Model\ModelFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_ProductFitmentFinder::model';

    private ModelFactory $modelFactory;

    public function __construct(Context $context, ModelFactory $modelFactory)
    {
        parent::__construct($context);
        $this->modelFactory = $modelFactory;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        try {
            $id = (int)($data['model_id'] ?? 0);
            $model = $this->modelFactory->create();
            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    throw new \RuntimeException(__('This model no longer exists.')->__toString());
                }
            }
            $makeId = (int)($data['make_id'] ?? 0);
            $name   = trim((string)($data['name'] ?? ''));
            if ($makeId <= 0) {
                throw new \RuntimeException(__('Please select a Make.')->__toString());
            }
            if ($name === '') {
                throw new \RuntimeException(__('Model name is required.')->__toString());
            }
            $model->setMakeId($makeId);
            $model->setName($name);
            $model->setSortOrder((int)($data['sort_order'] ?? 0));

            /* Pre-check for duplicate (make_id + name) so the user sees a friendly message */
            $conn = $model->getResource()->getConnection();
            $table = $model->getResource()->getMainTable();
            $existingId = (int)$conn->fetchOne(
                "SELECT model_id FROM $table WHERE make_id = ? AND LOWER(name) = LOWER(?) LIMIT 1",
                [$makeId, $name]
            );
            if ($existingId && $existingId !== $id) {
                throw new \RuntimeException(__('A model named "%1" already exists under this make. Pick a different name or edit the existing one.', $name)->__toString());
            }

            $model->save();

            $this->messageManager->addSuccessMessage(__('Model saved.'));
            if ($this->getRequest()->getParam('back') === 'edit') {
                return $redirect->setPath('*/*/edit', ['model_id' => $model->getId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'integrity constraint') !== false || stripos($msg, 'duplicate') !== false) {
                $msg = __('A model with that name already exists under this make.')->__toString();
            }
            $this->messageManager->addErrorMessage($msg);
            return $redirect->setPath('*/*/edit', ['model_id' => $id ?? null]);
        }
    }
}
