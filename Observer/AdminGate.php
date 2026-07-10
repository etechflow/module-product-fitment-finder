<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Observer;

use ETechFlow\ProductFitmentFinder\Model\LicenseValidator;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Freezes the module's admin feature pages when the licence is not valid.
 *
 * Wired on controller_action_predispatch_etechflow_vehicle (see
 * etc/adminhtml/events.xml). When isValid() is false it stops the action and
 * redirects to the licence gate page, which renders a state-aware notice
 * (suspended / expired / blocked / licence-required). The gate controller
 * itself is exempted so we never redirect-loop, and Stores -> Configuration
 * lives on a different route so it stays reachable for pasting the key.
 */
class AdminGate implements ObserverInterface
{
    public function __construct(
        private readonly LicenseValidator $licenseValidator,
        private readonly UrlInterface $backendUrl,
        private readonly ResponseInterface $response,
        private readonly ActionFlag $actionFlag
    ) {
    }

    public function execute(Observer $observer): void
    {
        $request = $observer->getRequest();

        // The gate page (and any license/* action) must stay reachable.
        if ($request !== null && $request->getControllerName() === 'license') {
            return;
        }

        if ($this->licenseValidator->isValid()) {
            return;
        }

        $this->actionFlag->set('', \Magento\Framework\App\Action\Action::FLAG_NO_DISPATCH, true);
        $this->response->setRedirect($this->backendUrl->getUrl('etechflow_vehicle/license/gate'));
    }
}
