<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options for the admin "Show Part Finder On" dropdown (single select).
 *
 * The chosen value gates which storefront placement of the Find-Your-Parts form
 * renders — see Model\Config::getPartFinderLocation() and the vc_location arg on
 * the PartFinder layout blocks. The dedicated /vehiclecompat/find page stays as
 * the results destination regardless of this setting.
 */
class PartFinderLocation implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '',             'label' => __('— Don\'t auto-place (use the widget) —')],
            ['value' => 'home',         'label' => __('Home Page')],
            ['value' => 'product',      'label' => __('Product Page')],
            ['value' => 'product_list', 'label' => __('Product List / Category Pages')],
        ];
    }
}
