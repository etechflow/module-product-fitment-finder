<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Block;

use ETechFlow\ProductFitmentFinder\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * "My Garage" widget — shows the customer's saved vehicles so they can
 * one-click reload a previously-picked selection back into the Part
 * Finder.
 *
 * v1.1.0 MVP: localStorage-based, guest + logged-in customer alike.
 * Vehicles are saved client-side and persist across pages/sessions
 * until the customer clears their browser data. v1.2.0+ adds customer
 * attribute storage for logged-in users so the garage syncs across
 * devices.
 *
 * Renders nothing when:
 *   - module disabled
 *   - admin opted out (Enable Customer Garage = No)
 * Otherwise renders a small widget that reads the saved vehicles from
 * localStorage and displays them with one-click reload buttons.
 *
 * Merchants place the widget anywhere via layout XML or CMS block:
 *   <block class="ETechFlow\ProductFitmentFinder\Block\Garage"
 *          name="header.garage"
 *          template="ETechFlow_ProductFitmentFinder::garage/widget.phtml"/>
 */
class Garage extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isSavedGarageEnabled();
    }

    public function getMaxEntries(): int
    {
        return $this->config->getGarageMaxEntries();
    }

    public function getStorageKey(): string
    {
        // Customer-side only — Magento store id ensures different store views
        // don't share garages (different catalogs, different vehicle ids).
        return 'etechflow_vc_garage_v1_store_' . (int) $this->_storeManager->getStore()->getId();
    }

    /** v1.1.1 — Admin-configurable customer-facing copy. */
    public function getGarageEmptyPrompt(): string { return $this->config->getGarageEmptyPrompt(); }

    /** v1.2.1 — polish copy + tooltip + accent colour. */
    public function getGarageTitle(): string        { return $this->config->getGarageTitle(); }
    public function getGarageClearButton(): string  { return $this->config->getGarageClearButton(); }
    public function getGarageRemoveLabel(): string  { return $this->config->getGarageRemoveLabel(); }
    public function getGarageTooltip(): string      { return $this->config->getGarageTooltip(); }
    public function getAccentColour(): string       { return $this->config->getAccentColour(); }
}
