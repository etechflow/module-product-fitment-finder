<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\Source;

use ETechFlow\ProductFitmentFinder\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class Year implements OptionSourceInterface
{
    /**
     * @deprecated since v1.0.2 — use Config::getEarliestYear() instead.
     *             Kept on disk only so any third-party code referencing
     *             the constant doesn't immediately break.
     */
    public const MIN_YEAR = 1990;

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        $max = (int) date('Y') + 1;
        $min = $this->config->getEarliestYear();
        for ($y = $max; $y >= $min; $y--) {
            $options[] = ['value' => $y, 'label' => (string) $y];
        }
        return $options;
    }
}
