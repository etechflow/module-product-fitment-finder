<?php
declare(strict_types=1);

namespace ETechFlow\ProductFitmentFinder\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Reverse of Controller\Router\FitmentRouter: turns numeric make/model/year/part
 * ids back into the SEO-friendly path "<prefix>/<make>/<model>/<year>/<part>".
 *
 * Used to canonicalise the finder's query-string URL (?make_id=…) and any old
 * ?make_id= links to the pretty URL via a 301. The slug rule MUST mirror the
 * router's lookup exactly (LOWER(REPLACE(name,' ','-'))) so the generated path
 * resolves back to the same ids.
 *
 * Returns null when the combination can't be expressed in the positional schema
 * (e.g. a year without a model), a name can't be resolved, or SEO URLs are off —
 * the caller then leaves the query-string URL as-is (no redirect).
 */
class SeoUrlBuilder
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config
    ) {
    }

    public function buildPath(int $makeId, ?int $modelId, ?int $year, ?int $partId): ?string
    {
        if (!$this->config->isEnabled() || !$this->config->isSeoUrlsEnabled()) {
            return null;
        }
        $prefix = $this->config->getSeoUrlPrefix();
        if ($prefix === '' || $makeId <= 0) {
            return null;
        }
        // Positional schema: make / model / year / part. A deeper segment
        // requires every shallower one, else the URL can't be built validly.
        if (($partId && (!$modelId || !$year)) || ($year && !$modelId)) {
            return null;
        }

        $conn = $this->resource->getConnection();
        $makeName = $conn->fetchOne(
            "SELECT name FROM " . $this->resource->getTableName('etechflow_vehicle_make') . " WHERE make_id = ?",
            [$makeId]
        );
        if (!$makeName) {
            return null;
        }
        $segments = [$prefix, $this->slug((string) $makeName)];

        if ($modelId) {
            $modelName = $conn->fetchOne(
                "SELECT name FROM " . $this->resource->getTableName('etechflow_vehicle_model')
                . " WHERE model_id = ? AND make_id = ?",
                [$modelId, $makeId]
            );
            if (!$modelName) {
                return null;
            }
            $segments[] = $this->slug((string) $modelName);
        }
        if ($year) {
            $segments[] = (string) $year;
        }
        if ($partId) {
            $eav = $this->resource->getTableName('eav_attribute');
            $attrId = (int) $conn->fetchOne(
                "SELECT attribute_id FROM $eav WHERE entity_type_id = 4 AND attribute_code = 'parts_required'"
            );
            $partName = $attrId ? $conn->fetchOne(
                "SELECT v.value FROM " . $this->resource->getTableName('eav_attribute_option_value') . " v"
                . " INNER JOIN " . $this->resource->getTableName('eav_attribute_option') . " o ON o.option_id = v.option_id"
                . " WHERE o.attribute_id = ? AND v.store_id = 0 AND v.option_id = ?",
                [$attrId, $partId]
            ) : false;
            if (!$partName) {
                return null;
            }
            $segments[] = $this->slug((string) $partName);
        }

        return implode('/', $segments);
    }

    /** Mirror FitmentRouter's lookup: LOWER(REPLACE(name, ' ', '-')). No trim. */
    private function slug(string $name): string
    {
        return strtolower(str_replace(' ', '-', $name));
    }
}
