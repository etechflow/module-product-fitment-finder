<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Controller\Adminhtml\Make;

use ETechFlow\ProductFitmentFinder\Model\MakeFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_ProductFitmentFinder::make';

    private MakeFactory $makeFactory;

    public function __construct(Context $context, MakeFactory $makeFactory)
    {
        parent::__construct($context);
        $this->makeFactory = $makeFactory;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }

        try {
            $id = (int)($data['make_id'] ?? 0);
            $model = $this->makeFactory->create();
            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    throw new \RuntimeException(__('This make no longer exists.')->__toString());
                }
            }
            $model->setName(trim((string)($data['name'] ?? '')));
            $model->setSortOrder((int)($data['sort_order'] ?? 0));

            if ($model->getName() === '') {
                throw new \RuntimeException(__('Make name is required.')->__toString());
            }

            /* Pre-check for duplicate name */
            $conn = $model->getResource()->getConnection();
            $table = $model->getResource()->getMainTable();
            $existingId = (int)$conn->fetchOne(
                "SELECT make_id FROM $table WHERE LOWER(name) = LOWER(?) LIMIT 1",
                [$model->getName()]
            );
            if ($existingId && $existingId !== $id) {
                throw new \RuntimeException(__('A make named "%1" already exists. Pick a different name or edit the existing one.', $model->getName())->__toString());
            }

            $model->save();

            $this->messageManager->addSuccessMessage(__('Make saved.'));
            if ($this->getRequest()->getParam('back') === 'edit') {
                return $redirect->setPath('*/*/edit', ['make_id' => $model->getId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'integrity constraint') !== false || stripos($msg, 'duplicate') !== false) {
                $msg = __('A make with that name already exists.')->__toString();
            }
            $this->messageManager->addErrorMessage($msg);
            return $redirect->setPath('*/*/edit', ['make_id' => $id ?? null]);
        }
    }
}
