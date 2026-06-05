<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BadgeStyle implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'success', 'label' => __('Success (green)')],
            ['value' => 'info',    'label' => __('Info (blue)')],
            ['value' => 'warning', 'label' => __('Warning (amber)')],
            ['value' => 'neutral', 'label' => __('Neutral (grey)')],
        ];
    }
}
