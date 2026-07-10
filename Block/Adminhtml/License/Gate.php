<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Block\Adminhtml\License;

use ETechFlow\ProductFitmentFinder\Model\LicenseValidator;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * View model for the admin licence gate. Exposes the read-only licence state so
 * gate.phtml can show a precise reason (suspended / expired / blocked / none)
 * without ever touching the stored key.
 */
class Gate extends Template
{
    public function __construct(
        Context $context,
        private readonly LicenseValidator $licenseValidator,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getLicenseState(): string
    {
        return $this->licenseValidator->getLicenseState();
    }

    /** URL of the module's Stores -> Configuration section (where the key is pasted). */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'etechflow_vehiclecompat']);
    }
}
