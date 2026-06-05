<?php

declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Block\Product;

use ETechFlow\ProductFitmentFinder\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * "This fits: …" fitment badge on the product detail page.
 *
 * Reads the product's `vehicle_compat_data` attribute (JSON written by
 * the admin VehicleCompat picker), resolves Make/Model IDs back to
 * names via the etechflow_vehicle_make / etechflow_vehicle_model
 * tables, formats per-vehicle ranges (e.g. "BMW 3 Series 2018-2023"),
 * and renders an inline coloured block above the buy box.
 *
 * Renders nothing when:
 *   - module disabled
 *   - admin opted out (Show Fitment Badge on PDP = No)
 *   - product has no vehicle_compat_data
 *   - product has data but no vehicles can be resolved
 *
 * Theme-agnostic: same template renders correctly on Hyvä and Luma.
 */
class FitmentBadge extends Template
{
    /**
     * Maximum vehicle entries to render inline. Anything above this
     * shows "and N more" rather than scrolling a giant list on the PDP.
     */
    private const INLINE_LIMIT = 3;

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly Registry $registry,
        private readonly ResourceConnection $resource,
        private readonly SerializerInterface $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isVisible(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }
        if (!$this->config->isShowFitmentBadgeOnPdp()) {
            return false;
        }
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return false;
        }
        return $this->getResolvedFitments() !== [];
    }

    /**
     * Customer-facing rendered HTML. Returns escaped strings only.
     *
     * @return array{prefix:string, fitments:string[], more:int, more_text:string, style:string, tooltip:string}
     */
    public function getRenderData(): array
    {
        $fitments = $this->getResolvedFitments();
        $more = max(0, count($fitments) - self::INLINE_LIMIT);
        return [
            'prefix'    => $this->config->getFitmentBadgePrefix(),
            'fitments'  => array_slice($fitments, 0, self::INLINE_LIMIT),
            'more'      => $more,
            // v1.2.1: configurable overflow template with {count} placeholder.
            'more_text' => $more > 0 ? $this->config->getFitmentOverflow($more) : '',
            'style'     => $this->config->getFitmentBadgeStyle(),
            // v1.2.1: optional customer-facing tooltip (empty string = no tooltip).
            'tooltip'   => $this->config->getFitmentTooltip(),
        ];
    }

    /**
     * @return string[]  e.g. ["BMW 3 Series 2018-2023", "BMW 5 Series 2020"]
     */
    private function getResolvedFitments(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }
        $raw = (string) $product->getData('vehicle_compat_data');
        if ($raw === '') {
            return [];
        }
        try {
            $data = $this->serializer->unserialize($raw);
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($data) || $data === []) {
            return [];
        }

        // Bulk-resolve Make + Model IDs in two SQL roundtrips, then format.
        $makeIds = [];
        $modelIds = [];
        foreach ($data as $entry) {
            if (isset($entry['make_id'])) {
                $makeIds[(int) $entry['make_id']] = true;
            }
            if (isset($entry['model_id'])) {
                $modelIds[(int) $entry['model_id']] = true;
            }
        }
        if ($makeIds === []) {
            return [];
        }

        $conn = $this->resource->getConnection();
        $makeNames = $conn->fetchPairs(
            $conn->select()
                ->from($this->resource->getTableName('etechflow_vehicle_make'), ['make_id', 'name'])
                ->where('make_id IN (?)', array_keys($makeIds))
        );
        $modelNames = [];
        if ($modelIds !== []) {
            $modelNames = $conn->fetchPairs(
                $conn->select()
                    ->from($this->resource->getTableName('etechflow_vehicle_model'), ['model_id', 'name'])
                    ->where('model_id IN (?)', array_keys($modelIds))
            );
        }

        $strings = [];
        foreach ($data as $entry) {
            $makeId  = (int) ($entry['make_id'] ?? 0);
            $modelId = (int) ($entry['model_id'] ?? 0);
            $years   = isset($entry['years']) && is_array($entry['years']) ? $entry['years'] : [];

            $makeName  = (string) ($makeNames[$makeId]  ?? '');
            $modelName = (string) ($modelNames[$modelId] ?? '');
            if ($makeName === '') {
                continue;
            }

            $parts = [$makeName];
            if ($modelName !== '') {
                $parts[] = $modelName;
            }
            if ($years !== []) {
                $parts[] = $this->formatYearRange($years);
            }
            $strings[] = implode(' ', $parts);
        }

        // De-dupe while preserving order — different `parts_required` entries
        // on the same Make/Model/Year set produce identical strings.
        return array_values(array_unique($strings));
    }

    /**
     * Compress an array of years into a human-readable range.
     * [2018,2019,2020,2021,2022,2023] → "2018-2023"
     * [2018,2020,2022] → "2018, 2020, 2022"
     */
    private function formatYearRange(array $years): string
    {
        $years = array_values(array_unique(array_map('intval', $years)));
        sort($years);
        if ($years === []) {
            return '';
        }
        // Check if contiguous
        $isContiguous = true;
        for ($i = 1, $n = count($years); $i < $n; $i++) {
            if ($years[$i] !== $years[$i - 1] + 1) {
                $isContiguous = false;
                break;
            }
        }
        if ($isContiguous) {
            return count($years) === 1
                ? (string) $years[0]
                : $years[0] . '-' . end($years);
        }
        return implode(', ', $years);
    }

    private function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }
}
